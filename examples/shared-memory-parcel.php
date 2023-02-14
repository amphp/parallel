#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\ByteStream;
use Amp\Sync\PosixSemaphore;
use Amp\Sync\SemaphoreMutex;
use Amp\Sync\SharedMemoryParcel;
use function Amp\async;
use function Amp\delay;

$mutex = new SemaphoreMutex($semaphore = PosixSemaphore::create(1));

// Create a parcel that then can be accessed in any number of child processes.
$parcel = SharedMemoryParcel::create($mutex, 1);

printf("Parent %d created semaphore %s and parcel: %s\n", getmypid(), $semaphore->getKey(), $parcel->getKey());

// Send semaphore and parcel key to child process as command argument.
$context = contextFactory()->start([
    __DIR__ . "/contexts/parcel-process.php",
    (string) $semaphore->getKey(),
    (string) $parcel->getKey(),
]);

// Pipe any data written to the STDOUT in the child process to STDOUT of this process.
async(fn () => ByteStream\pipe($context->getStdout(), ByteStream\getStdout()));

delay(0.5); // Give the process time to start and access the parcel.

$parcel->synchronized(function (int $value): int {
    return $value + 1;
});

$context->join(); // Wait for child process to finish.

printf("Final value of parcel: %d\n", $parcel->unwrap());
