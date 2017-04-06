<?php

namespace AppBundle\Helper;

use DateInterval;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\FluentRequest;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApiHelper extends HelperBase
{

    /** @var MediawikiApi */
    private $api;

    /** @var LabsHelper */
    private $labsHelper;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /** @var ContainerInterface */
    protected $container;

    public function __construct(ContainerInterface $container, LabsHelper $labsHelper)
    {
        $this->container = $container;
        $this->labsHelper = $labsHelper;
        $this->cache = $container->get('cache.app');
    }

    private function setUp($project)
    {
        if (!isset($this->api)) {
            $projectInfo = $this->labsHelper->databasePrepare($project);

            try {
                if (strpos($projectInfo["url"], "api.php") !== false) {
                    $this->api = MediawikiApi::newFromApiEndpoint($projectInfo["url"]);
                } else {
                    $this->api = MediawikiApi::newFromPage($projectInfo['url']);
                }
            } catch (Exception $e) {
                // Do nothing...
            } catch (FatalErrorException $e) {
                // Do nothing...
            }
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

    /**
     * Get a list of namespaces on the given project.
     *
     * @param string $project
     * @return string[] Array of namespace IDs (keys) to names (values).
     */
    public function namespaces($project)
    {
        $cacheKey = "namespaces.$project";
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

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
            $this->cacheSave($cacheKey, $result, 'P7D');
        } catch (Exception $e) {
            // The api returned an error!  Ignore
        }

        return $result;
    }

    public function getAdmins($project)
    {
        $params = [
            'list' => 'allusers',
            'augroup' => 'sysop|bureaucrat|steward|oversight|checkuser',
            'auprop' => 'groups',
            'aulimit' => '500',
        ];

        $result = [];
        $admins = $this->massApi($params, $project, 'allusers', 'aufrom')['allusers'];

        foreach ($admins as $admin) {
            $groups = [];
            if (in_array("sysop", $admin["groups"])) {
                $groups[] = "A";
            }
            if (in_array("bureaucrat", $admin["groups"])) {
                $groups[] = "B";
            }
            if (in_array("steward", $admin["groups"])) {
                $groups[] = "S" ;
            }
            if (in_array("checkuser", $admin["groups"])) {
                $groups[] = "CU";
            }
            if (in_array("oversight", $admin["groups"])) {
                $groups[] = "OS";
            }
            if (in_array("bot", $admin["groups"])) {
                $groups[] = "Bot";
            }
            $result[ $admin["name"] ] = [
                "groups" => implode('/', $groups)
            ];
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
     * Get assessments of the given pages, if a supported project
     * @param  string       $project    Project such as en.wikipedia.org
     * @param  string|array $pageTitles Single page title or array of titles
     * @return array|null               Page assessments info or null if none found
     */
    public function getPageAssessments($project, $pageTitles)
    {
        $supportedProjects = ['en.wikipedia.org', 'en.wikivoyage.org'];

        // return null if unsupported project
        if (!in_array($project, $supportedProjects)) {
            return null;
        }

        $params = [
            'prop' => 'pageassessments',
            'titles' => is_string($pageTitles) ? $pageTitles : implode('|', $pageTitles),
            'palimit' => 500,
        ];

        // get assessments for this page from the API
        $assessments = $this->massApi($params, $project, function ($data) {
            return isset($data['pages'][0]['pageassessments']) ? $data['pages'][0]['pageassessments'] : [];
        }, 'pacontinue')['pages'];

        // From config/assessments.yml
        $config = $this->getAssessmentsConfig()[$project];

        $decoratedAssessments = [];

        // Set the default decorations for the overall quality assessment
        // This will be replaced with the first valid class defined for any WikiProject
        $overallQuality = $config['class']['Unknown'];
        $overallQuality['value'] = '???';

        if (empty($assessments)) {
            return null;
        }

        // loop through each assessment and decorate with colors, category URLs and images, if applicable
        foreach ($assessments as $wikiproject => $assessment) {
            $classValue = $assessment['class'];

            // Use ??? as the presented value when the class is unknown or is not defined in the config
            if ($classValue === 'Unknown' || $classValue === '' || !isset($config['class'][$classValue])) {
                $classAttrs = $config['class']['Unknown'];
                $assessment['class']['value'] = '???';
                $assessment['class']['category'] = $classAttrs['category'];
                $assessment['class']['badge'] = "https://upload.wikimedia.org/wikipedia/commons/". $classAttrs['badge'];
            } else {
                $classAttrs = $config['class'][$classValue];
                $assessment['class'] = [
                    'value' => $classValue,
                    'color' => $classAttrs['color'],
                    'category' => $classAttrs['category'],
                ];

                // add full URL to badge icon
                if ($classAttrs['badge'] !== '') {
                    $assessment['class']['badge'] = "https://upload.wikimedia.org/wikipedia/commons/" .
                        $classAttrs['badge'];
                }

                if ($overallQuality['value'] === '???') {
                    $overallQuality = $assessment['class'];
                    $overallQuality['category'] = $classAttrs['category'];
                }
            }

            $importanceValue = $assessment['importance'];
            $importanceUnknown = $importanceValue === 'Unknown' || $importanceValue === '';

            if ($importanceUnknown || !isset($config['importance'][$importanceValue])) {
                $importanceAttrs = $config['importance']['Unknown'];
                $assessment['importance'] = $importanceAttrs;
                $assessment['importance']['value'] = '???';
                $assessment['importance']['category'] = $importanceAttrs['category'];
            } else {
                $importanceAttrs = $config['importance'][$importanceValue];
                $assessment['importance'] = [
                    'value' => $importanceValue,
                    'color' => $importanceAttrs['color'],
                    'weight' => $importanceAttrs['weight'], // numerical weight for sorting purposes
                    'category' => $importanceAttrs['category'],
                ];
            }

            $decoratedAssessments[$wikiproject] = $assessment;
        }

        return [
            'assessment' => $overallQuality,
            'wikiprojects' => $decoratedAssessments,
            'wikiproject_prefix' => $config['wikiproject_prefix']
        ];
    }

    /**
     * Get the base path to WikiProjects for the given wiki
     * @return string Path, such as 'Wikipedia:WikiProject_'
     */
    public function getWikiProjectPrefix($project)
    {
        return $this->getAssessmentsConfig()[$project]['wikiproject_prefix'];
    }

    /**
     * Fetch assessments data from config/assessments.yml and cache in static variable
     * @return array Mappings of project/quality/class with badges, colors and category links
     */
    private function getAssessmentsConfig()
    {
        static $assessmentsConfig = null;
        if ($assessmentsConfig === null) {
            $assessmentsConfig = $this->container->getParameter('assessments');
        }
        return $assessmentsConfig;
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
     * @param  string      [$continueKey] the key to look in the continue hash, if present
     *                                    (e.g. 'cmcontinue' for API:Categorymembers)
     * @param  integer     [$limit]       Max number of pages to fetch
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
                if ($result['continue']) {
                    $data['resolveData']['continue'] = true;
                }
                $data['promise']->resolve($data);
            }
        });
    }
}
