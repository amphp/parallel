#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\HelloTask;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

Coroutine\create(function () {
    $returnValues = (yield Promise\all([
        new Coroutine\Coroutine(Worker\enqueue(new HelloTask())),
        new Coroutine\Coroutine(Worker\enqueue(new HelloTask())),
        new Coroutine\Coroutine(Worker\enqueue(new HelloTask())),
    ]));
    var_dump($returnValues);

    yield Worker\pool()->shutdown();
})->done();

Loop\run();
