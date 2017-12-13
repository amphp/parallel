#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Coroutine;
use Amp\Loop;
use Amp\Parallel\Example\BlockingTask;
use Amp\Parallel\Worker\DefaultPool;

// A variable to store our fetched results
$results = [];

// We can first define tasks and then run them
$tasks = [
    new BlockingTask('file_get_contents', 'http://php.net'),
    new BlockingTask('file_get_contents', 'https://amphp.org'),
    new BlockingTask('file_get_contents', 'https://github.com'),
];

// Event loop for parallel tasks
Loop::run(function () use (&$results, &$tasks) {
    $timer = Loop::repeat(200, function () {
        printf(".");
    });
    Loop::unreference($timer);

    $pool = new DefaultPool;

    $coroutines = [];

    foreach ($tasks as $task) {
        $coroutines[] = function () use ($pool, $task, &$results) {
            $result = yield $pool->enqueue($task);
            $url = $task->getArgs()[0];
            printf("\nRead from %s: %d bytes\n", $url, strlen($result));
            $results[$url] = $result;
        };
    }

    $coroutines = array_map(function (callable $coroutine): Coroutine {
        return new Coroutine($coroutine());
    }, $coroutines);

    yield Amp\Promise\all($coroutines);

    return yield $pool->shutdown();
});

echo "\nResult array keys:\n";
echo var_export(array_keys($results), true);
