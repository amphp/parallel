#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Concurrent\Forking\Fork;

Amp\execute(function () {
    $context = Fork::spawn(function () {
        print "Child sleeping for 4 seconds...\n";
        sleep(4);

        yield $this->send('Data sent from child.');

        print "Child sleeping for 2 seconds...\n";
        sleep(2);

        return 42;
    });

    $timer = Amp\repeat(1000, function () use ($context) {
        static $i;
        $i = $i ? ++$i : 1;
        print "Demonstrating how alive the parent is for the {$i}th time.\n";
    });

    try {
        printf("Received the following from child: %s\n", yield $context->receive());
        printf("Child ended with value %d!\n", yield $context->join());
    } catch (Throwable $e) {
        print "Error from child!\n";
        print $e."\n";
    } finally {
        Amp\cancel($timer);
    }
});
