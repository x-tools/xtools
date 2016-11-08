<?php

namespace AppBundle\Helper;


class Apihelper
{
    public function test() {
        dump("Testing");
    }

    public function groups($host, $username) {
        $array = @file_get_contents("$host/w/api.php?action=query&list=users&ususers=$username&usprop=groups&format=json");

        dump($array);
    }

    public function globalGroups($host, $username) {
        $retVal = [];

        $array = @file_get_contents("$host/api.php?action=query&meta=globaluserinfo&guiuser=$username&guiprop=groups&format=json");

        if ($array === false) return $retVal;

        $data = json_decode($array, true);

        if (!isset($data["warnings"]) && !isset($data["error"])) {
            $retVal = $data["query"]["globaluserinfo"]["groups"];
        }

        return $retVal;

    }

    public function namespaces($host) {
        $retVal = [];

        $array = @file_get_contents("$host/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json");

        if ($array === false || $array === null) return $retVal;

        $data = json_decode($array, true);

        if (!isset($data["warnings"]) && !isset($data["error"])) {
            foreach ($data["query"]["namespaces"] as $row) {
                if($row["id"] < 0) {continue;}

                if (isset($row["name"])) {$name = $row["name"];}
                elseif (isset($row["*"])) {$name = $row["*"];}
                else {continue;}

                if($name === "") {$name = "Main"; }

                $retVal[$row["id"]] = $name;
            }
        }

        return $retVal;

    }
}
