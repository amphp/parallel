#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker\Pool;
use Icicle\Coroutine\Coroutine;
use Icicle\Examples\Concurrent\BlockingTask;
use Icicle\Loop;
use Icicle\Promise;

$generator = function () {
    $pool = new Pool();
    $pool->start();

    $results = (yield Promise\all([
        'google.com' => new Coroutine($pool->enqueue(new BlockingTask('file_get_contents', 'https://google.com'))),
        'icicle.io'  => new Coroutine($pool->enqueue(new BlockingTask('file_get_contents', 'https://icicle.io'))),
    ]));

    foreach ($results as $source => $result) {
        printf("Read from %s: %d bytes\n", $source, strlen($result));
    }

    yield $pool->shutdown();
};

$coroutine = new Coroutine($generator());
$coroutine->done();

Loop\periodic(0.1, function () {
    printf(".\n");
})->unreference();

Loop\run();
