#!/usr/bin/env php
<?php

// Redirect all output written using echo, print, printf, etc. to STDERR.
ob_start(function ($data) {
    fwrite(STDERR, $data);
    return '';
}, 1, 0);

$paths = [
    dirname(dirname(dirname(__DIR__))) . '/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $autoloadPath = $path;
        break;
    }
}

if (!isset($autoloadPath)) {
    fwrite(STDERR, 'Could not locate autoload.php.');
    exit(1);
}

require $autoloadPath;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Concurrent\Worker\Internal\TaskRunner;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\ReadableStream;
use Icicle\Socket\Stream\WritableStream;

Coroutine\create(function () {
    $channel = new Channel(new ReadableStream(STDIN), new WritableStream(STDOUT));

    $runner = new TaskRunner($channel);

    try {
        $result = new ExitSuccess(yield $runner->run());
    } catch (Exception $exception) {
        $result = new ExitFailure($exception);
    }

    yield $channel->send($result);

    $channel->close();
})->done();

Loop\run();
