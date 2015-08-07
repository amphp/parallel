#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Threading\ThreadContext;
use Icicle\Coroutine;
use Icicle\Loop;

$timer = Loop\periodic(1, function () {
    print "Demonstrating how alive the parent is.\n";
});

Coroutine\create(function () {
    // Create a periodic message in the main thread.

    // Create a new child thread that does some blocking stuff.
    $test = new ThreadContext(function () {
        print "Sleeping for 5 seconds...\n";
        sleep(5);
        return 42;
    });

    // Run the thread and wait asynchronously for it to finish.
    $test->start();
    printf("Thread ended with value %d!\n", (yield $test->join()));
})->done([$timer, 'stop']);

Loop\run();
