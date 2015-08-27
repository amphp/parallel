#!/usr/bin/env php
<?php

$paths = [
    dirname(dirname(__DIR__)) . '/autoload.php',
    dirname(__DIR__ ). '/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $autoloadPath = $path;
        break;
    }
}

if (!isset($autoloadPath)) {
    fwrite(STDERR, 'Could not find autoloader include path.');
    exit(-1);
}

require $autoloadPath;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Worker\Internal\TaskRunner;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\ReadableStream;
use Icicle\Socket\Stream\WritableStream;

// Redirect all output written using echo, print, printf, etc. to STDERR.
ob_start(function ($data) {
    fwrite(STDERR, $data);
    return '';
}, 1);

$runner = new TaskRunner(new Channel(new ReadableStream(STDIN), new WritableStream(STDOUT)));

$coroutine = new Coroutine($runner->run());
$coroutine->done();

try {
    Loop\run();
} catch (Exception $exception) {
    fwrite(STDERR, sprintf(
        'Exception of type %s thrown in process with message "%s"',
        get_class($exception),
        $exception->getMessage()
    ));
    exit(-1);
}
