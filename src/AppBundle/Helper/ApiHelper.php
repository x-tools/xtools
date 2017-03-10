<?php

namespace AppBundle\Helper;

use \Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use GuzzleHttp;
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
}
