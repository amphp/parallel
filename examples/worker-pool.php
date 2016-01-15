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

    $coroutines = [];

    $coroutines[] = Coroutine\create(function () use ($pool) {
        $url = 'https://google.com';
        $result = (yield $pool->enqueue(new BlockingTask('file_get_contents', $url)));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    });

    $coroutines[] = Coroutine\create(function () use ($pool) {
        $url = 'https://icicle.io';
        $result = (yield $pool->enqueue(new BlockingTask('file_get_contents', $url)));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    });

    $coroutines[] = Coroutine\create(function () use ($pool) {
        $url = 'https://github.com';
        $result = (yield $pool->enqueue(new BlockingTask('file_get_contents', $url)));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    });

    yield Awaitable\all($coroutines);

    yield $pool->shutdown();
})->done();

Loop\periodic(0.1, function () {
    printf(".\n");
})->unreference();

Loop\run();
