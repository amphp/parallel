#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker\TaskInterface;
use Icicle\Concurrent\Worker\WorkerThread;
use Icicle\Coroutine;
use Icicle\Loop;

class HelloTask implements TaskInterface
{
    public function run()
    {
        echo "Hello!\n";
        return 42;
    }
}

Coroutine\create(function () {
    $worker = new WorkerThread();
    $worker->start();

    $returnValue = (yield $worker->enqueue(new HelloTask()));
    yield $worker->shutdown();
});

Loop\run();
