<?php

namespace AppBundle\Helper;


class Apihelper
{
    private $curlChannel;

    private function curl($url, $timeout = 90)
    {
        if ( !$this->curlChannel )
        {
            $ch = $this->curlChannel = curl_init();
            curl_setopt($ch, CURLOPT_USERAGENT, "Xtools" ); //TODO: Turn into config option
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $ch = $this->curlChannel;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , $timeout);

        if (curl_error($ch) !== "")
        {
            return false;
        }

        return curl_exec($ch);
    }

    public function __construct()
    {
        $ch = $this->curlChannel = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Xtools" ); // TODO: Turn into config option
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

    public function test()
    {
        dump("Testing");
    }

    public function groups($host, $username)
    {
        $array = $this->curl("$host/w/api.php?action=query&list=users&ususers=$username&usprop=groups&format=json");

        dump($array);
    }

    public function globalGroups($host, $username)
    {
        $retVal = [];

        $array = $this->curl("$host/api.php?action=query&meta=globaluserinfo&guiuser=$username&guiprop=groups&format=json");

        if ($array === false) return $retVal;

        $data = json_decode($array, true);

        if (!isset($data["warnings"]) && !isset($data["error"]) && isset($data["query"]["globaluserinfo"]["groups"])) {
            $retVal = $data["query"]["globaluserinfo"]["groups"];
        }

        return $retVal;

    }

    public function namespaces($host)
    {
        $retVal = [];

        $array = $this->curl("$host/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json");

        if ($array === false || $array === null) return $retVal;

        $data = json_decode($array, true);

        if (!isset($data["warnings"]) && !isset($data["error"]) && isset($data["query"]["namespaces"])) {
            foreach ($data["query"]["namespaces"] as $row) {
                if($row["id"] < 0) {continue;}

                if (isset($row["name"])) {$name = $row["name"];}
                elseif (isset($row["*"])) {$name = $row["*"];}
                else {continue;}

                // TODO: Figure out a way to i18n-ize this
                if($name === "") {$name = "Article"; }

                $retVal[$row["id"]] = $name;
            }
        }

        return $retVal;

    }
}
