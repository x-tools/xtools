<?php

namespace AppBundle\Helper;


class Apihelper
{
    public function test() {
        dump("Testing");
    }

    public function groups($host, $username) {
        $array = file_get_contents("$host/w/api.php?action=query&list=users&ususers=$username&usprop=groups&format=json");

        dump($array);
    }

    public function globalGroups($host, $username) {
        $retVal = [];

        $array = file_get_contents("$host/api.php?action=query&meta=globaluserinfo&guiuser=$username&guiprop=groups&format=json");

        $data = json_decode($array, true);

        if (!isset($data["warnings"]) && !isset($data["error"])) {
            $retVal = $data["query"]["globaluserinfo"]["groups"];
        }

        return $retVal;

    }
}