#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Forking\ForkContext;
use Icicle\Coroutine;
use Icicle\Loop;

Coroutine\create(function () {
    $context = new ForkContext(function () {
        print "Child sleeping for 4 seconds...\n";
        sleep(4);

        print "Child sleeping for 2 seconds...\n";
        sleep(2);

        return 42;
    });
    $context->start();

    $timer = Loop\periodic(1, function () use ($context) {
        static $i;
        $i = $i + 1 ?: 1;
        print "Demonstrating how alive the parent is for the {$i}th time.\n";
    });

    try {
        printf("Child ended with value %d!\n", (yield $context->join()));
    } catch (Exception $e) {
        print "Error from child!\n";
        print $e."\n";
    } finally {
        $timer->stop();
    }
});

Loop\run();
