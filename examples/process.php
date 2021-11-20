#!/usr/bin/env php
<?php
require \dirname(__DIR__).'/vendor/autoload.php';

use Amp\ByteStream;
use Amp\Parallel\Context\Process;
use Revolt\EventLoop;
use function Amp\launch;
use function Amp\delay;

$timer = EventLoop::repeat(1, function () {
    static $i;
    $i = $i ? ++$i : 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

try {
    // Create a new child process that does some blocking stuff.
    $context = Process::run(__DIR__ . "/blocking-process.php");

    \assert($context instanceof Process);

    // Pipe any data written to the STDOUT in the child process to STDOUT of this process.
    $future = launch(fn () => ByteStream\pipe($context->getStdout(), ByteStream\getStdout()));

    print "Waiting 2 seconds to send start data...\n";
    delay(2);

    $context->send("Start data"); // Data sent to child process, received on line 9 of blocking-process.php

    \printf("Received the following from child: %s\n", $context->receive()); // Sent on line 14 of blocking-process.php
    \printf("Process ended with value %d!\n", $context->join());
} finally {
    EventLoop::cancel($timer);
}
