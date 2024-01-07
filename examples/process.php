#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\ByteStream;
use Amp\Parallel\Context\ProcessContextFactory;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

$timer = EventLoop::repeat(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    $nth = $i . ([1 => 'st', 2 => 'nd', 3 => 'rd'][$i] ?? 'th');
    print "Demonstrating how alive the parent is for the {$nth} time.\n";
});

// This example is identical to context.php, but uses a ProcessContext to demonstrate piping STDOUT
// of the child process to STDOUT of the parent process.

try {
    // Create a new child process that does some blocking stuff.
    $context = (new ProcessContextFactory())->start(__DIR__ . "/contexts/blocking.php");

    // Pipe any data written to the STDOUT in the child process to STDOUT of this process.
    $future = async(fn () => ByteStream\pipe($context->getStdout(), ByteStream\getStdout()));

    print "Waiting 2 seconds to send start data...\n";
    delay(2);

    $context->send("Start data"); // Data sent to child process, received on line 9 of contexts/blocking.php

    printf("Received the following from child: %s\n", $context->receive()); // Sent on line 14 of contexts/blocking.php
    printf("Process ended with value %d!\n", $context->join());
} finally {
    EventLoop::cancel($timer);
}
