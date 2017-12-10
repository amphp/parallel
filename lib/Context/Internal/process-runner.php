<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Loop;
use Amp\Parallel\Sync;
use function Amp\call;

// Doesn't exist in phpdbg...
if (\function_exists("cli_set_process_title")) {
    @\cli_set_process_title("amp-process");
}

// Redirect all output written using echo, print, printf, etc. to STDERR.
\ob_start(function ($data) {
    \fwrite(STDERR, $data);
    return '';
}, 1, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);

(function () {
    $paths = [
        \dirname(__DIR__, 5) . "/autoload.php",
        \dirname(__DIR__, 3) . "/vendor/autoload.php",
    ];

    foreach ($paths as $path) {
        if (\file_exists($path)) {
            $autoloadPath = $path;
            break;
        }
    }

    if (!isset($autoloadPath)) {
        \fwrite(STDERR, "Could not locate autoload.php");
        exit(1);
    }

    require $autoloadPath;
})();

Loop::run(function () use ($argc, $argv) {
    $channel = new Sync\ChannelledSocket(STDIN, STDOUT);

    // Remove this scripts path from process arguments.
    --$argc;
    \array_shift($argv);

    try {
        // Protect current scope by requiring script within another function.
        $callable = (function () use ($argc, $argv): callable {
            if (!isset($argv[0])) {
                throw new \Error("No script path given");
            }

            if (!\is_file($argv[0])) {
                throw new \Error(\sprintf("No script found at '%s' (be sure to provide the full path to the script)", $argv[0]));
            }

            $callable = require $argv[0];

            if (!\is_callable($callable)) {
                throw new \Error("Script did not return a callable function");
            }

            return $callable;
        })();

        $result = new Sync\ExitSuccess(yield call($callable, $channel));
    } catch (Sync\ChannelException $exception) {
        exit(1); // Parent context died, simply exit.
    } catch (\Throwable $exception) {
        $result = new Sync\ExitFailure($exception);
    }

    try {
        yield $channel->send($result);
    } catch (\Throwable $exception) {
        exit(1); // Parent context died, simply exit.
    }
});
