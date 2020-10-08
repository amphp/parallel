#!/usr/bin/env php
<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Parallel\Worker;
use function Amp\async;
use function Amp\await;

$urls = [
    'https://secure.php.net',
    'https://amphp.org',
    'https://github.com',
];

$promises = [];
foreach ($urls as $url) {
    $promises[$url] = async(fn () => Worker\enqueueCallable('file_get_contents', $url));
}

$responses = await($promises);

foreach ($responses as $url => $response) {
    \printf("Read %d bytes from %s\n", \strlen($response), $url);
}
