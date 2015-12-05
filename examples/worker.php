#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Worker\DefaultWorkerFactory;
use Icicle\Coroutine;
use Icicle\Examples\Concurrent\BlockingTask;
use Icicle\Loop;

Coroutine\create(function () {
    $factory = new DefaultWorkerFactory();

    $worker = $factory->create();
    $worker->start();

    $result = (yield $worker->enqueue(new BlockingTask('file_get_contents', 'https://google.com')));
    printf("Read %d bytes\n", strlen($result));

    $code = (yield $worker->shutdown());
    printf("Code: %d\n", $code);
})->done();

Loop\run();
