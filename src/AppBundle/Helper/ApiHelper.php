<?php

namespace AppBundle\Helper;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\FluentRequest;
use Symfony\Component\Config\Definition\Exception\Exception;

class ApiHelper
{
    private $api;

    private function setUp($project)
    {
        if (!isset($this->api)) {
            $this->api = MediawikiApi::newFromApiEndpoint("$project/w/api.php");
        }
    }

    public function groups($project, $username)
    {

        $this->setUp($project);
        $params = [ "list"=>"users", "ususers"=>$username, "usprop"=>"groups" ];
        $query = new SimpleRequest('query', $params);
        $result = [];

        try {
            $res = $this->api->getRequest($query);
            if (isset($res["batchcomplete"]) && isset($res["query"]["users"][0]["groups"])) {
                $result = $res["query"]["users"][0]["groups"];
            }
        } catch (Exception $e) {
            // The api returned an error!  Ignore
        }

        return $result;
    }

    public function globalGroups($project, $username)
    {

        $this->setUp($project);
        $params = [ "meta"=>"globaluserinfo", "guiuser"=>$username, "guiprop"=>"groups" ];
        $query = new SimpleRequest('query', $params);
        $result = [];

        try {
            $res = $this->api->getRequest($query);
            if (isset($res["batchcomplete"]) && isset($res["query"]["globaluserinfo"]["groups"])) {
                $result = $res["query"]["globaluserinfo"]["groups"];
            }
        } catch (Exception $e) {
            // The api returned an error!  Ignore
        }

        return $result;
    }

    public function namespaces($project)
    {

        $this->setUp($project);
        $query = new SimpleRequest('query', [ "meta"=>"siteinfo", "siprop"=>"namespaces" ]);
        $result = [];

        try {
            $res = $this->api->getRequest($query);
            if (isset($res["batchcomplete"]) && isset($res["query"]["namespaces"])) {
                foreach ($res["query"]["namespaces"] as $row) {
                    if ($row["id"] < 0) {
                        continue;
                    }

                    if (isset($row["name"])) {
                        $name = $row["name"];
                    } elseif (isset($row["*"])) {
                        $name = $row["*"];
                    } else {
                        continue;
                    }

                    // TODO: Figure out a way to i18n-ize this
                    if ($name === "") {
                        $name = "Article";
                    }

                    $result[$row["id"]] = $name;
                }
            }
        } catch (Exception $e) {
            // The api returned an error!  Ignore
        }

        return $result;
    }

    /**
     * Get basic info about a page via the API
     * @param  string  $project      Full domain of project (en.wikipedia.org)
     * @param  string  $page         Page title
     * @param  boolean $followRedir  Whether or not to resolve redirects
     * @return array   Associative array of data
     */
    public function getBasicPageInfo($project, $page, $followRedir)
    {
        $this->setUp($project);

        // @TODO: Also include 'extlinks' prop when we start checking for dead external links.
        $params = [
            'prop' => 'info|pageprops',
            'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|readable',
            'converttitles' => '',
            // 'ellimit' => 20,
            // 'elexpandurl' => '',
            'titles' => $page,
            'formatversion' => 2
            // 'pageids' => $pageIds // FIXME: allow page IDs
        ];

        if ($followRedir) {
            $params['redirects'] = '';
        }

        $query = new SimpleRequest('query', $params);
        $result = [];

        try {
            $res = $this->api->getRequest($query);
            if (isset($res['query']['pages'])) {
                $result = $res['query']['pages'][0];
            }
        } catch (Exception $e) {
            // The api returned an error!  Ignore
        }

        return $result;
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
        for ($n = 0; $n < count($pageTitles); $n += 50) {
            $titleSlice = array_slice($pageTitles, $n, 50);
            $params = [
                'prop' => 'info|pageprops',
                'inprop' => 'displaytitle',
                'titles' => join('|', $titleSlice),
            ];
            $query = new SimpleRequest('query', $params);
            $result = $this->api->getRequest($query);

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
     * @param  array   $params        Associative array of params to pass to API
     * @param  string  $project       Project to query, e.g. en.wikipedia.org
     * @param  string  [$continueKey] the key to look in the continue hash, if present
     *                                (e.g. 'cmcontinue' for API:Categorymembers)
     * @param  string  $dataKey       The key for the main chunk of data, in the query hash
     *                                (e.g. 'categorymembers' for API:Categorymembers)
     * @param  integer $limit         max number of pages to fetch
     * @return array                  Associative array with data
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

            // append new data to data from last request. We might want both 'pages' and dataKey
            if ($result['query']['pages']) {
                $data['resolveData']['pages'] = array_merge($data['resolveData']['pages'], $result['query']['pages']);
            }
            if ($result['query'][$dataKey]) {
                $newValues = isset($data['resolveData'][$dataKey]) ? $data['resolveData'][$dataKey] : [];
                $data['resolveData'][$dataKey] = array_merge($newValues, $result['query'][$dataKey]);
            }

            // If pages is not the collection we want, it will be either an empty array or one entry with
            //   basic page info depending on what API we're hitting. So resolveData[dataKey] will hit the limit
            $isFinished = count($data['resolveData']['pages']) >= $data['limit'] ||
                count($data['resolveData'][$dataKey]) >= $data['limit'];

            // make recursive call if needed, waiting 100ms
            if (!$isFinished && isset($result['continue']) && isset($result['continue'][$data['continueKey']])) {
                usleep(100000);
                $data['continueValue'] = $result['continue'][$data['continueKey']];
                return $this->massApiInternal($data);
            } else {
                // indicate there were more entries than the limit
                if ($result['continue']) {
                    $data['resolveData']['continue'] = true;
                }
                $data['promise']->resolve($data);
            }
        });
    }
}
