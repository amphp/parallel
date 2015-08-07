#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Threading\ThreadContext;
use Icicle\Coroutine;
use Icicle\Loop;

$timer = Loop\periodic(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

Coroutine\create(function () {
    // Create a periodic message in the main thread.

    // Create a new child thread that does some blocking stuff.
    $context = new ThreadContext(function () {
        print "Sleeping for 3 seconds...\n";
        sleep(3);

        yield $this->send('Data sent from child.');

        print "Sleeping for 2 seconds...\n";
        sleep(2);

        yield 42;
    });

    // Run the thread and wait asynchronously for it to finish.
    $context->start();

    printf("Received the following from child: %s\n", (yield $context->receive()));
    printf("Thread ended with value %d!\n", (yield $context->join()));
})->cleanup([$timer, 'stop']);

Loop\run();
