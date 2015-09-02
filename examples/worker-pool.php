#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\HelloTask;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

$generator = function () {
    $returnValues = (yield Promise\all([
        new Coroutine(Worker\enqueue(new HelloTask())),
        new Coroutine(Worker\enqueue(new HelloTask())),
        new Coroutine(Worker\enqueue(new HelloTask())),
    ]));

    var_dump($returnValues);
};

$coroutine = new Coroutine($generator());
$coroutine->done();

Loop\run();
