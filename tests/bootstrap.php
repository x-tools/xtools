<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$loadFile = '/.env.test';

if (file_exists(dirname(__DIR__) . '/.env')) {
    // So integration (database-interacting) tests can be ran in local environments.
    $loadFile = '/.env';
}

(new Dotenv())->load(dirname(__DIR__) . $loadFile);
