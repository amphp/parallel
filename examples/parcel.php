#!/usr/bin/env php
<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\Parcel;
use Amp\Parallel\Sync\ThreadedParcel;

Loop::run(function () {
    $parcel = new ThreadedParcel(1);

    $context = Thread::run(function (Channel $channel, Parcel $parcel) {
        $value = yield $parcel->synchronized(function (int $value) {
            return $value + 1;
        });

        printf("Value after modifying in child thread: %s\n", $value);

        yield new Delayed(2000); // Main thread should access parcel during this time.

        // Unwrapping the parcel now should give value from main thread.
        printf("Value in child thread after being modified in main thread: %s\n", yield $parcel->unwrap());

        yield $parcel->synchronized(function (int $value) {
            return $value + 1;
        });
    }, $parcel);

    yield new Delayed(1000); // Give the thread time to start and access the parcel.

    yield $parcel->synchronized(function (int $value) {
        return $value + 1;
    });

    yield $context->join(); // Wait for child thread to finish.

    printf("Final value of parcel: %d\n", yield $parcel->unwrap());
});
