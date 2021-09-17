#!/usr/bin/env php
<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Future;
use Amp\Parallel\Worker\CallableTask;
use Amp\Parallel\Worker\DefaultPool;
use Revolt\EventLoop\Loop;

// A variable to store our fetched results
$results = [];

// We can first define tasks and then run them
$tasks = [
    new CallableTask('file_get_contents', ['http://php.net']),
    new CallableTask('file_get_contents', ['https://amphp.org']),
    new CallableTask('file_get_contents', ['https://github.com']),
];

// Event loop for parallel tasks
$timer = Loop::repeat(0.2, function () {
    \printf(".");
});
Loop::unreference($timer);

$pool = new DefaultPool;

$futures = [];

foreach ($tasks as $index => $task) {
    $futures[] = Future\spawn(function () use ($pool, $index, $task): string {
        $result = $pool->enqueue($task);
        \printf("\nRead from task %d: %d bytes\n", $index, \strlen($result));
        return $result;
    });
}

$results = Future\all($futures);

$pool->shutdown();
