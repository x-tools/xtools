<?php

namespace AppBundle\Helper;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
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
        $displayTitles = [];
        for ($n = 0; $n < count($pageTitles); $n += 50) {
            $titleSlice = array_slice($pageTitles, $n, 50);
            $params = [
                'prop' => 'info|pageprops',
                'inprop' => 'displaytitle',
                'titles' => join('|', $titleSlice),
            ];
            $query = new SimpleRequest('query', $params);
            $result = $this->getApi($project)->getRequest($query);

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
}
