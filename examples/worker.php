#!/usr/bin/env php
<?php
require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Parallel\Worker\CallableTask;
use Amp\Parallel\Worker\DefaultWorkerFactory;

$factory = new DefaultWorkerFactory;

$worker = $factory->create();

$result = $worker->enqueue(new CallableTask('file_get_contents', ['https://google.com']));
\printf("Read %d bytes\n", \strlen($result));

$code = $worker->shutdown();
\printf("Code: %d\n", $code);
