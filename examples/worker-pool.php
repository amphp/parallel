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
        Worker\enqueue(new HelloTask()),
        Worker\enqueue(new HelloTask()),
        Worker\enqueue(new HelloTask()),
    ]));

    var_dump($returnValues);
})->done();

Loop\run();
