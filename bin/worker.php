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

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\Internal\ExitFailure;
use Icicle\Concurrent\Sync\Internal\ExitSuccess;
use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Internal\TaskRunner;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Stream;

Coroutine\create(function () {
    $channel = new Channel(Stream\stdin(), Stream\stdout());
    $environment = new Environment();

    $runner = new TaskRunner($channel, $environment);

    try {
        $result = new ExitSuccess(yield $runner->run());
    } catch (Exception $exception) {
        $result = new ExitFailure($exception);
    }

    yield $channel->send($result);
})->done();

Loop\run();
