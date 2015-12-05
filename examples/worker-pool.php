#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Awaitable;
use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Coroutine\Coroutine;
use Icicle\Examples\Concurrent\BlockingTask;
use Icicle\Loop;

$generator = function () {
    $pool = new DefaultPool();
    $pool->start();

    $results = (yield Awaitable\all([
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
