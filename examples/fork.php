<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Forking\ForkContext;
use Icicle\Concurrent\Task;
use Icicle\Coroutine\Coroutine;

$task = new Task(function () {
    print "Exiting in 5 seconds...\n";
    sleep(5);
    print "Context exiting...\n";
});

$context = new ForkContext();
new Coroutine($context->run($task));

print "Context started.\n";

Icicle\Loop\periodic(1, function () {
    static $i;
    $i = $i + 1 ?: 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

Icicle\Loop\run();
