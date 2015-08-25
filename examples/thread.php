#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Threading\Thread;
use Icicle\Coroutine;
use Icicle\Loop;

$timer = Loop\periodic(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

Coroutine\create(function () {
    // Create a new child thread that does some blocking stuff.
    $context = Thread::spawn(function () {
        printf("\$this: %s\n", get_class($this));

        printf("Received the following from parent: %s\n", (yield $this->receive()));

        yield $this->synchronized(function () {
            print "Sleeping for 3 seconds...\n";
            sleep(3);

            yield $this->send('Data sent from child.');

            print "Sleeping for 2 seconds...\n";
            sleep(2);
        });

        yield 42;
    });

    yield $context->send('Start data');

    yield $context->synchronized(function () {
        printf("Cooperatively sleeping in parent for 2 seconds before releasing lock...\n");

        yield Coroutine\sleep(2);
    });

    printf("Received the following from child: %s\n", (yield $context->receive()));
    printf("Thread ended with value %d!\n", (yield $context->join()));
})->cleanup([$timer, 'stop'])->done();

Loop\run();
