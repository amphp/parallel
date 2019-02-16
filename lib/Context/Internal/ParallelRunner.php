<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Loop;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ExitFailure;
use Amp\Parallel\Sync\ExitSuccess;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use function Amp\call;

final class ParallelRunner
{
    const EXIT_CHECK_FREQUENCY = 250;

    public static function unserializeArguments(string $arguments): array
    {
        \set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if ($errno & \error_reporting()) {
                throw new ChannelException(\sprintf(
                    'Received corrupted data. Errno: %d; %s in file %s on line %d',
                    $errno,
                    $errstr,
                    $errfile,
                    $errline
                ));
            }
        });

        // Attempt to unserialize function arguments
        try {
            $arguments = \unserialize($arguments);
        } catch (\Throwable $exception) {
            throw new SerializationException("Exception thrown when unserializing data", 0, $exception);
        } finally {
            \restore_error_handler();
        }

        if (!\is_array($arguments)) {
            throw new SerializationException("Argument list did not unserialize to an array");
        }

        return \array_values($arguments);
    }

    public static function execute(Channel $channel, string $path, string $arguments): int
    {
        Loop::unreference(Loop::repeat(self::EXIT_CHECK_FREQUENCY, function () {
            // Timer to give the chance for the PHP VM to be interrupted by Runtime::kill(), since system calls such as
            // select() will not be interrupted.
        }));

        try {
            if (!\is_file($path)) {
                throw new \Error(\sprintf("No script found at '%s' (be sure to provide the full path to the script)", $path));
            }

            try {
                // Protect current scope by requiring script within another function.
                $callable = (function () use ($path): callable {
                    return require $path;
                })();
            } catch (\TypeError $exception) {
                throw new \Error(\sprintf("Script '%s' did not return a callable function", $path), 0, $exception);
            } catch (\ParseError $exception) {
                throw new \Error(\sprintf("Script '%s' contains a parse error", $path), 0, $exception);
            }

            $arguments = self::unserializeArguments($arguments);

            $result = new ExitSuccess(Promise\wait(call($callable, $channel, ...$arguments)));
        } catch (\Throwable $exception) {
            $result = new ExitFailure($exception);
        }

        try {
            Promise\wait(call(function () use ($channel, $result) {
                try {
                    yield $channel->send($result);
                } catch (SerializationException $exception) {
                    // Serializing the result failed. Send the reason why.
                    yield $channel->send(new ExitFailure($exception));
                }
            }));
        } catch (\Throwable $exception) {
            \trigger_error("Could not send result to parent; be sure to shutdown the child before ending the parent", E_USER_ERROR);
            return 1;
        }

        return 0;
    }
}
