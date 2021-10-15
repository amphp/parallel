#!/usr/bin/env php
<?php
require \dirname(__DIR__).'/vendor/autoload.php';

use Amp\Parallel\Context\Parallel;
use Revolt\EventLoop;
use function Amp\delay;

$timer = EventLoop::repeat(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

try {
    // Create a new child thread that does some blocking stuff.
    $context = Parallel::run(__DIR__ . "/blocking-process.php");

    \assert($context instanceof Parallel);

    print "Waiting 2 seconds to send start data...\n";
    delay(2);

    $context->send("Start data"); // Data sent to child process, received on line 9 of blocking-process.php

    \printf("Received the following from child: %s\n", $context->receive()); // Sent on line 14 of blocking-process.php
    \printf("Process ended with value %d!\n", $context->join());
} finally {
    EventLoop::cancel($timer);
}
