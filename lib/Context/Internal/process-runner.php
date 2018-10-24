<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Loop;
use Amp\Parallel\Context\Process;
use Amp\Parallel\Sync;
use function Amp\call;

// Doesn't exist in phpdbg...
if (\function_exists("cli_set_process_title")) {
    @\cli_set_process_title("amp-process");
}

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
        \trigger_error(E_USER_ERROR, "Could not locate autoload.php in any of the following files: " . \implode(", ", $paths));
        exit(1);
    }

    require $autoloadPath;
})();

Loop::run(function () use ($argc, $argv) {
    // Remove this scripts path from process arguments.
    --$argc;
    \array_shift($argv);

    if (!isset($argv[0])) {
        throw new \Error("No socket path provided");
    }

    // Remove socket path from process arguments.
    --$argc;
    $uri = \array_shift($argv);

    $key = "";

    // Read random key from STDIN and send back to parent over IPC socket to authenticate.
    do {
        if (($chunk = \fread(\STDIN, Process::KEY_LENGTH)) === false || \feof(\STDIN)) {
            \trigger_error(E_USER_ERROR, "Could not read key from parent");
            exit(1);
        }
        $key .= $chunk;
    } while (\strlen($key) < Process::KEY_LENGTH);

    if (!$socket = \stream_socket_client($uri, $errno, $errstr, 5, \STREAM_CLIENT_CONNECT)) {
        \trigger_error(E_USER_ERROR, "Could not connect to IPC socket");
        exit(1);
    }

    $channel = new Sync\ChannelledSocket($socket, $socket);

    try {
        yield $channel->send($key);
    } catch (\Throwable $exception) {
        \trigger_error(E_USER_ERROR, "Could not send key to parent");
        exit(1);
    }

    try {
        if (!isset($argv[0])) {
            throw new \Error("No script path given");
        }

        if (!\is_file($argv[0])) {
            throw new \Error(\sprintf("No script found at '%s' (be sure to provide the full path to the script)", $argv[0]));
        }

        // Protect current scope by requiring script within another function.
        $callable = (function () use ($argc, $argv) { // Using $argc so it is available to the required script.
            return require $argv[0];
        })();

        if (!\is_callable($callable)) {
            throw new \Error(\sprintf("Script '%s' did not return a callable function", $argv[0]));
        }

        $result = new Sync\ExitSuccess(yield call($callable, $channel));
    } catch (\Throwable $exception) {
        $result = new Sync\ExitFailure($exception);
    }

    try {
        try {
            yield $channel->send($result);
        } catch (Sync\SerializationException $exception) {
            // Serializing the result failed. Send the reason why.
            yield $channel->send(new Sync\ExitFailure($exception));
        }
    } catch (\Throwable $exception) {
        \trigger_error(E_USER_ERROR, "Could not send result to parent");
        exit(1);
    }
});
