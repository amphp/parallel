#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Parallel\Example\BlockingTask;
use Amp\Parallel\Worker\DefaultWorkerFactory;

Amp\Loop::run(function () {
    $factory = new DefaultWorkerFactory();

    $worker = $factory->create();

    $result = yield $worker->enqueue(new BlockingTask('file_get_contents', 'https://google.com'));
    printf("Read %d bytes\n", strlen($result));

    $code = yield $worker->shutdown();
    printf("Code: %d\n", $code);
});
