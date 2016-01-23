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

        printf("Received the following from parent: %s\n", yield from $this->receive());

        print "Sleeping for 3 seconds...\n";
        sleep(3); // Blocking call in thread.

        yield from $this->send('Data sent from child.');

        print "Sleeping for 2 seconds...\n";
        sleep(2); // Blocking call in thread.

        return 42;
    });

    print "Waiting 2 seconds to send start data...\n";
    yield Coroutine\sleep(2);

    yield from $context->send('Start data');

    printf("Received the following from child: %s\n", yield from $context->receive());
    printf("Thread ended with value %d!\n", yield from $context->join());
})->cleanup([$timer, 'stop'])->done();

Loop\run();
