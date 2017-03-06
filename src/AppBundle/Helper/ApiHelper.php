<?php

namespace AppBundle\Helper;


use \Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use GuzzleHttp;
use Symfony\Component\Config\Definition\Exception\Exception;

class ApiHelper
{
    private $api;

    private function setUp($host) {
        if (!isset($this->api)) {
            $this->api = MediawikiApi::newFromApiEndpoint( "$host/api.php" );
        }

    }

    public function groups($host, $username)
    {
        $this->setUp($host);
        $query = new SimpleRequest('query', ["list"=>"users", "ususers"=>$username, "usprop"=>"groups"]);
        $result = [];

        try{
            $res = $this->api->getRequest( $query );
            if (isset($res["batchcomplete"]) && isset($res["query"]["users"][0]["groups"])) {
                $result = $res["query"]["users"][0]["groups"];
            }
        }
        catch ( Exception $e ) {
            // The api returned an error!  Ignore
        }

        return $result;
    }

    public function globalGroups($host, $username)
    {
        $this->setUp($host);
        $query = new SimpleRequest('query', ["meta"=>"globaluserinfo", "guiuser"=>$username, "guiprop"=>"groups"]);
        $result = [];

        try{
            $res = $this->api->getRequest( $query );
            if (isset($res["batchcomplete"]) && isset($res["query"]["globaluserinfo"]["groups"])) {
                $result = $res["query"]["globaluserinfo"]["groups"];
            }
        }
        catch ( Exception $e ) {
            // The api returned an error!  Ignore
        }

        return $result;

    }

    public function namespaces($host)
    {
        $this->setUp($host);
        $query = new SimpleRequest('query', ["meta"=>"siteinfo", "siprop"=>"namespaces"]);
        $result = [];

        try{
            $res = $this->api->getRequest( $query );
            if (isset($res["batchcomplete"]) && isset($res["query"]["namespaces"])) {
                foreach ($res["query"]["namespaces"] as $row) {
                    if($row["id"] < 0) {continue;}

                    if (isset($row["name"])) {$name = $row["name"];}
                    elseif (isset($row["*"])) {$name = $row["*"];}
                    else {continue;}

                    // TODO: Figure out a way to i18n-ize this
                    if($name === "") {$name = "Article"; }

                    $result[$row["id"]] = $name;
                }
            }
        }
        catch ( Exception $e ) {
            // The api returned an error!  Ignore
        }

        return $result;

    }
}
