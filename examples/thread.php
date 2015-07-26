<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Concurrent\Threading\ThreadContext;
use Icicle\Loop;

// Create a periodic message in the main thread.
$timer = Loop\periodic(1, function () {
    print "Demonstrating how alive the parent is.\n";
});

// Create a new child thread that does some blocking stuff.
$test = new ThreadContext(function () {
    print "Sleeping for 5 seconds...\n";
    sleep(5);
});

// Run the thread and wait asynchronously for it to finish.
$test->start();
$test->join()->then(function () use ($test) {
    print "Thread ended!\n";
    Loop\stop();
});

Loop\run();
