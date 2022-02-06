<?php

// The function returned by this script is run by shared-memory-process.php in a separate process.
// $argc and $argv are available in this process as any other cli PHP script.

use Amp\Sync\PosixSemaphore;
use Amp\Sync\SemaphoreMutex;
use Amp\Sync\SharedMemoryParcel;
use function Amp\delay;

return function () use ($argv): int {
    if (!isset($argv[1])) {
        throw new \Error("No semaphore ID provided");
    }

    if (!isset($argv[2])) {
        throw new \Error("No parcel ID provided");
    }

    $semaphoreKey = (int) $argv[1];
    $parcelKey = (int) $argv[2];

    \printf("Child process using semaphore %s and parcel %s\n", $semaphoreKey, $parcelKey);

    $mutex = new SemaphoreMutex(PosixSemaphore::use($semaphoreKey));
    $parcel = SharedMemoryParcel::use($mutex, $parcelKey);

    $value = $parcel->synchronized(function (int $value): int {
        return $value + 1;
    });

    \printf("Value after modifying in child process: %s\n", $value);

    delay(1); // Parent process should access parcel during this time.

    // Unwrapping the parcel now should give value from parent process.
    \printf("Value in child process after being modified in parent process: %s\n", $parcel->unwrap());

    $value = $parcel->synchronized(function (int $value): int {
        return $value + 1;
    });

    \printf("Value after modifying in child process: %s\n", $value);

    return $value;
};
