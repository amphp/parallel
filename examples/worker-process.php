#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker\HelloTask;
use Icicle\Concurrent\Worker\WorkerProcess;
use Icicle\Coroutine;
use Icicle\Loop;

Coroutine\create(function () {
    $worker = new WorkerProcess();
    $worker->start();

    $returnValue = (yield $worker->enqueue(new HelloTask()));

    printf("Return value: %s\n", $returnValue);

    $code = (yield $worker->shutdown());
    printf("Code: %d\n", $code);
})->done();

Loop\run();
