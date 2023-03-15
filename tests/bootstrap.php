<?php

declare(strict_types = 1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$loadFile = '/.env.test';

if (file_exists(dirname(__DIR__) . '/.env.test.local')) {
    // So integration (database-interacting) tests can be ran in local environments.
    $loadFile = '/.env.test.local';
}

(new Dotenv(false))->load(dirname(__DIR__) . $loadFile);
