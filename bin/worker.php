#!/usr/bin/env php
<?php

// Redirect all output written using echo, print, printf, etc. to STDERR.
ob_start(function ($data) {
    fwrite(STDERR, $data);
    return '';
}, 1, 0);

$paths = [
    dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'autoload.php',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
];

$autoloadPath = null;

foreach ($paths as $path) {
    if (file_exists($path)) {
        $autoloadPath = $path;
        break;
    }
}

if (null === $autoloadPath) {
    fwrite(STDERR, 'Could not locate autoload.php.');
    exit(1);
}

require $autoloadPath;

use Icicle\Concurrent\Sync\ChannelledStream;
use Icicle\Concurrent\Sync\Internal\ExitFailure;
use Icicle\Concurrent\Sync\Internal\ExitSuccess;
use Icicle\Concurrent\Worker\BasicEnvironment;
use Icicle\Concurrent\Worker\Internal\TaskRunner;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Stream;

Coroutine\create(function () {
    $channel = new ChannelledStream(Stream\stdin(), Stream\stdout());
    $environment = new BasicEnvironment();
    $runner = new TaskRunner($channel, $environment);

    try {
        $result = new ExitSuccess(yield $runner->run());
    } catch (Exception $exception) {
        $result = new ExitFailure($exception);
    }

    // Attempt to return the result.
    try {
        yield $channel->send($result);
    } catch (Exception $exception) {
        // The result was not sendable! The parent context must have died or killed the context.
        yield 0;
    }
})->done();

Loop\run();
