<?php
declare(strict_types = 1);

use Symfony\Component\HttpFoundation\Request;

/**
 * @var Composer\Autoload\ClassLoader
 */
$loader = require __DIR__.'/../src/autoload.php';

$kernel = new AppKernel('prod', false);

$request = Request::createFromGlobals();

// FIXME: should be configurable if we ever return to supporting 3rd party use of XTools
Request::setTrustedProxies(
    ['172.16.0.0/21'], // Wikimedia Cloud Services VPS
    Request::HEADER_X_FORWARDED_ALL
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
