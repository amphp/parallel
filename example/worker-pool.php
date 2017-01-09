#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Parallel\Worker\DefaultPool;
use Amp\Coroutine;
use Amp\Parallel\Example\BlockingTask;
use AsyncInterop\Loop;

Loop::execute(Amp\wrap(function() {
    $timer = Loop::repeat(100, function () {
        printf(".\n");
    });
    Loop::unreference($timer);
    
    $pool = new DefaultPool();
    $pool->start();

    $coroutines = [];

    $coroutines[] = function () use ($pool) {
        $url = 'https://google.com';
        $result = yield $pool->enqueue(new BlockingTask('file_get_contents', $url));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    };

    $coroutines[] = function () use ($pool) {
        $url = 'http://amphp.org';
        $result = yield $pool->enqueue(new BlockingTask('file_get_contents', $url));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    };

    $coroutines[] = function () use ($pool) {
        $url = 'https://github.com';
        $result = yield $pool->enqueue(new BlockingTask('file_get_contents', $url));
        printf("Read from %s: %d bytes\n", $url, strlen($result));
    };
    
    $coroutines = array_map(function (callable $coroutine): Coroutine {
        return new Coroutine($coroutine());
    }, $coroutines);

    yield Amp\all($coroutines);

    return yield $pool->shutdown();
}));

