#!/usr/bin/env php
<?php
require \dirname(__DIR__).'/vendor/autoload.php';

use Amp\ByteStream;
use Amp\Parallel\Context\Process;
use Amp\Parallel\Sync\SharedMemoryParcel;
use function Amp\delay;

// Create a parcel that then can be accessed in any number of child processes.
$parcel = SharedMemoryParcel::create($id = \bin2hex(\random_bytes(10)), 1);

$context = Process::run([
    __DIR__ . "/parcel-process.php",
    $id, // Send parcel ID to child process as command argument.
]);

\assert($context instanceof Process);

// Pipe any data written to the STDOUT in the child process to STDOUT of this process.
Amp\Promise\rethrow(ByteStream\pipe($context->getStdout(), ByteStream\getStdout()));

delay(100); // Give the process time to start and access the parcel.

$parcel->synchronized(function (int $value): int {
    return $value + 1;
});

$context->join(); // Wait for child process to finish.

\printf("Final value of parcel: %d\n", $parcel->unwrap());
