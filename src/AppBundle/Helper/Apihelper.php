<?php

namespace AppBundle\Helper;


class Apihelper
{
    public function test() {
        dump("Testing");
    }

    public function groups($host, $username) {
        $array = file_get_contents("$host/w/api.php?action=query&list=users&ususers=$username&usprop=groups");

        dump($array);
    }

    public function globalGroups($host, $username) {
        $array = file_get_contents("$host/w/api.php?action=query&meta=globaluserinfo&guiuser=$username&guiprop=groups");

        dump($array);

    }
}