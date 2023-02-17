#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Sync\PosixSemaphore;
use Amp\Sync\SemaphoreMutex;
use Amp\Sync\SharedMemoryParcel;
use function Amp\delay;
use function Amp\Parallel\Context\contextFactory;

$mutex = new SemaphoreMutex($semaphore = PosixSemaphore::create(1));

// Create a parcel that then can be accessed in any number of child processes or threads.
$parcel = SharedMemoryParcel::create($mutex, 1);

printf("Parent %d created semaphore %s and parcel: %s\n", getmypid(), $semaphore->getKey(), $parcel->getKey());

// Send semaphore and parcel key to child process as command argument.
$context = contextFactory()->start([
    __DIR__ . "/contexts/parcel.php",
    (string) $semaphore->getKey(),
    (string) $parcel->getKey(),
]);

delay(0.5); // Give the process or thread time to start and access the parcel.

$parcel->synchronized(function (int $value): int {
    return $value + 1;
});

$context->join(); // Wait for child process or thread to finish.

printf("Final value of parcel: %d\n", $parcel->unwrap());
