<?php
/**
 * This file contains only the ApiHelper class.
 */

namespace AppBundle\Helper;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\FluentRequest;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Xtools\ProjectRepository;

/**
 * This is a helper for calling the MediaWiki API.
 */
class ApiHelper extends HelperBase
{
    /** @var MediawikiApi The API object. */
    private $api;

    /** @var CacheItemPoolInterface The cache. */
    protected $cache;

    /** @var ContainerInterface The DI container. */
    protected $container;

    /**
     * ApiHelper constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = $container->get('cache.app');
    }

    /**
     * Set up the MediawikiApi object for the given project.
     *
     * @param string $project
     */
    private function setUp($project)
    {
        if (!$this->api instanceof MediawikiApi) {
            $project = ProjectRepository::getProject($project, $this->container);
            $this->api = $project->getApi();
        }
    }

    /**
     * Get HTML display titles of a set of pages (or the normal title if there's no display title).
     * This will send t/50 API requests where t is the number of titles supplied.
     * @param string $project The project.
     * @param string[] $pageTitles The titles to fetch.
     * @return string[] Keys are the original supplied title, and values are the display titles.
     */
    public function displayTitles($project, $pageTitles)
    {
        $this->setUp($project);
        $displayTitles = [];
        $numPages = count($pageTitles);
        for ($n = 0; $n < $numPages; $n += 50) {
            $titleSlice = array_slice($pageTitles, $n, 50);
            $params = [
                'prop' => 'info|pageprops',
                'inprop' => 'displaytitle',
                'titles' => join('|', $titleSlice),
            ];
            $query = new SimpleRequest('query', $params);
            $result = $this->api->postRequest($query);

            // Extract normalization info.
            $normalized = [];
            if (isset($result['query']['normalized'])) {
                array_map(
                    function ($e) use (&$normalized) {
                        $normalized[$e['to']] = $e['from'];
                    },
                    $result['query']['normalized']
                );
            }

            // Match up the normalized titles with the display titles and the original titles.
            foreach ($result['query']['pages'] as $pageInfo) {
                $displayTitle = isset($pageInfo['pageprops']['displaytitle'])
                    ? $pageInfo['pageprops']['displaytitle']
                    : $pageInfo['title'];
                $origTitle = isset($normalized[$pageInfo['title']])
                    ? $normalized[$pageInfo['title']] : $pageInfo['title'];
                $displayTitles[$origTitle] = $displayTitle;
            }
        }

        return $displayTitles;
    }

    /**
     * Make mass API requests to MediaWiki API
     * The API normally limits to 500 pages, but gives you a 'continue' value
     *   to finish iterating through the resource.
     * Adapted from https://github.com/MusikAnimal/pageviews
     * @param  array       $params        Associative array of params to pass to API
     * @param  string      $project       Project to query, e.g. en.wikipedia.org
     * @param  string|func $dataKey       The key for the main chunk of data, in the query hash
     *                                    (e.g. 'categorymembers' for API:Categorymembers).
     *                                    If this is a function it is given the response data,
     *                                    and expected to return the data we want to concatentate.
     * @param  string      $continueKey   The key to look in the continue hash, if present
     *                                    (e.g. 'cmcontinue' for API:Categorymembers)
     * @param  integer     $limit         Max number of pages to fetch
     * @return array                      Associative array with data
     */
    public function massApi($params, $project, $dataKey, $continueKey = 'continue', $limit = 5000)
    {
        $this->setUp($project);

        // Passed by reference to massApiInternal so we can keep track of
        //   everything we need during the recursive calls
        // The magically essential part here is $data['promise'] which we'll
        //   wait to be resolved
        $data = [
            'params' => $params,
            'project' => $project,
            'continueKey' => $continueKey,
            'dataKey' => $dataKey,
            'limit' => $limit,
            'resolveData' => [
                'pages' => []
            ],
            'continueValue' => null,
            'promise' => new \GuzzleHttp\Promise\Promise(),
        ];

        // wait for all promises to complete, even if some of them fail
        \GuzzleHttp\Promise\settle($this->massApiInternal($data))->wait();

        return $data['resolveData'];
    }

    /**
     * Internal function used by massApi() to make recursive calls
     * @param  array &$data Everything we need to keep track of, as defined in massApi()
     * @return null         Nothing. $data['promise']->then is used to continue flow of
     *                      execution after all recursive calls are complete
     */
    private function massApiInternal(&$data)
    {
        $requestData = array_merge([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
        ], $data['params']);

        if ($data['continueValue']) {
            $requestData[$data['continueKey']] = $data['continueValue'];
        }

        $query = FluentRequest::factory()->setAction('query')->setParams($requestData);
        $innerPromise = $this->api->getRequestAsync($query);

        $innerPromise->then(function ($result) use (&$data) {
            // some failures come back as 200s, so we still resolve and let the outer function handle it
            if (isset($result['error']) || !isset($result['query'])) {
                return $data['promise']->resolve($data);
            }

            $dataKey = $data['dataKey'];
            $isFinished = false;

            // allow custom function to parse the data we want, if provided
            if (is_callable($dataKey)) {
                $data['resolveData']['pages'] = array_merge(
                    $data['resolveData']['pages'],
                    $data['dataKey']($result['query'])
                );
                $isFinished = count($data['resolveData']['pages']) >= $data['limit'];
            } else {
                // append new data to data from last request. We might want both 'pages' and dataKey
                if (isset($result['query']['pages'])) {
                    $data['resolveData']['pages'] = array_merge(
                        $data['resolveData']['pages'],
                        $result['query']['pages']
                    );
                }
                if ($result['query'][$dataKey]) {
                    $newValues = isset($data['resolveData'][$dataKey]) ? $data['resolveData'][$dataKey] : [];
                    $data['resolveData'][$dataKey] = array_merge($newValues, $result['query'][$dataKey]);
                }

                // If pages is not the collection we want, it will be either an empty array or one entry with
                //   basic page info depending on what API we're hitting. So resolveData[dataKey] will hit the limit
                $isFinished = count($data['resolveData']['pages']) >= $data['limit'] ||
                    count($data['resolveData'][$dataKey]) >= $data['limit'];
            }

            // make recursive call if needed, waiting 100ms
            if (!$isFinished && isset($result['continue']) && isset($result['continue'][$data['continueKey']])) {
                usleep(100000);
                $data['continueValue'] = $result['continue'][$data['continueKey']];
                return $this->massApiInternal($data);
            } else {
                // indicate there were more entries than the limit
                if (isset($result['continue'])) {
                    $data['resolveData']['continue'] = true;
                }
                $data['promise']->resolve($data);
            }
        });
    }
}
