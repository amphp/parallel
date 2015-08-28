#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker\HelloTask;
use Icicle\Concurrent\Worker\WorkerPool;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

Coroutine\create(function () {
    $pool = new WorkerPool(1);

    $returnValues = (yield Promise\all([
        new Coroutine\Coroutine($pool->enqueue(new HelloTask())),
        new Coroutine\Coroutine($pool->enqueue(new HelloTask())),
        new Coroutine\Coroutine($pool->enqueue(new HelloTask())),
    ]));
    var_dump($returnValues);

    yield $pool->shutdown();
})->done();

Loop\run();
