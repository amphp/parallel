#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Awaitable;
use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Coroutine;
use Icicle\Examples\Concurrent\BlockingTask;
use Icicle\Loop;

Coroutine\create(function() {
    $pool = new DefaultPool();
    $pool->start();

    Coroutine\create(function () use ($pool) {
        $result = (yield $pool->enqueue(new BlockingTask('file_get_contents', 'https://google.com')));
        printf("Read from google.com: %d bytes\n", strlen($result));
    });

    Coroutine\create(function () use ($pool) {
        $result = (yield $pool->enqueue(new BlockingTask('file_get_contents', 'https://icicle.io')));
        printf("Read from icicle.io: %d bytes\n", strlen($result));
    });

    yield $pool->shutdown();
})->done();

Loop\periodic(0.1, function () {
    printf(".\n");
})->unreference();

Loop\run();
