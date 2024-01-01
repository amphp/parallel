#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Revolt\EventLoop;
use function Amp\delay;
use function Amp\Parallel\Context\contextFactory;

$timer = EventLoop::repeat(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    $nth = $i . ([1 => 'st', 2 => 'nd', 3 => 'rd'][$i] ?? 'th');
    print "Demonstrating how alive the parent is for the {$nth} time.\n";
});

try {
    // Create a new child process or thread that does some blocking stuff.
    $context = contextFactory()->start(__DIR__ . "/contexts/blocking.php");

    print "Waiting 2 seconds to send start data...\n";
    delay(2);

    $context->send("Start data"); // Data sent to child process, received on line 9 of contexts/blocking.php

    printf("Received the following from child: %s\n", $context->receive()); // Sent on line 14 of blocking.php
    printf("Process ended with value %d!\n", $context->join());
} finally {
    EventLoop::cancel($timer);
}
