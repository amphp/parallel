#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;

Loop::run(function () {
    $timer = Loop::repeat(1000, function () {
        static $i;
        $i = $i ? ++$i : 1;
        print "Demonstrating how alive the parent is for the {$i}th time.\n";
    });

    try {
        // Create a new child thread that does some blocking stuff.
        $context = Thread::spawn(function (Channel $channel): \Generator {
            printf("Received the following from parent: %s\n", yield $channel->receive());

            print "Sleeping for 3 seconds...\n";
            sleep(3); // Blocking call in thread.

            yield $channel->send("Data sent from child.");

            print "Sleeping for 2 seconds...\n";
            sleep(2); // Blocking call in thread.

            return 42;
        });

        print "Waiting 2 seconds to send start data...\n";
        yield new Delayed(2000);

        yield $context->send("Start data");

        printf("Received the following from child: %s\n", yield $context->receive());
        printf("Thread ended with value %d!\n", yield $context->join());
    } finally {
        Loop::cancel($timer);
    }
});
