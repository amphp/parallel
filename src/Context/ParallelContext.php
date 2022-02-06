<?php

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Future;
use Amp\Parallel\Context\Internal\ParallelHub;
use Amp\Parallel\Sync\IpcHub;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\Serialization\SerializationException;
use Amp\Sync\ChannelException;
use Amp\Sync\StreamChannel;
use Amp\TimeoutCancellation;
use parallel\Runtime;
use Revolt\EventLoop;
use function Amp\async;

/**
 * Implements an execution context using native threads provided by the parallel extension.
 *
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-implements Context<TResult, TReceive, TSend>
 */
final class ParallelContext implements Context
{
    private const EXIT_CHECK_FREQUENCY = 0.25;
    public const DEFAULT_START_TIMEOUT = 5;

    private static ?\WeakMap $hubs = null;

    /** @var string|null */
    private static ?string $autoloadPath = null;

    /** @var int Next thread ID. */
    private static int $nextId = 1;

    /**
     * Checks if threading is enabled.
     *
     * @return bool True if threading is enabled, otherwise false.
     */
    public static function isSupported(): bool
    {
        return \extension_loaded('parallel');
    }

    private int $id;

    private ?Runtime $runtime;

    /** @var StreamChannel|null A channel for communicating with the parallel thread. */
    private ?StreamChannel $channel;

    private int $oid;

    private bool $killed = false;

    /**
     * @param string|array $script Path to PHP script or array with first element as path and following elements options
     *     to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
     * @param IpcHub|null $hub Optional IpcHub instance.
     *
     * @return ParallelContext<TResult, TReceive, TSend>
     *
     * @throws \Error Thrown if the pthreads extension is not available.
     */
    public static function start(string|array $script, ?IpcHub $hub = null): self
    {
        if (!self::isSupported()) {
            throw new \Error("The parallel extension is required to create parallel threads.");
        }

        self::$hubs ??= new \WeakMap();
        $hub = (self::$hubs[EventLoop::getDriver()] ??= new Internal\ParallelHub($hub ?? ipcHub()));

        if (!\is_array($script)) {
            $script = [$script];
        }

        $command = (string) \array_shift($script);
        $args = \array_values(\array_map("strval", $script));

        if (self::$autoloadPath === null) {
            $paths = [
                \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . "vendor" . \DIRECTORY_SEPARATOR . "autoload.php",
                \dirname(__DIR__, 4) . \DIRECTORY_SEPARATOR . "autoload.php",
            ];

            foreach ($paths as $path) {
                if (\file_exists($path)) {
                    self::$autoloadPath = $path;
                    break;
                }
            }

            if (self::$autoloadPath === null) {
                throw new \Error("Could not locate autoload.php");
            }
        }

        $runtime = new Runtime(self::$autoloadPath);

        $id = self::$nextId++;

        $future = $runtime->run(static function (
            int $id,
            string $uri,
            string $key,
            string $path,
            array $argv
        ): int {
            // @codeCoverageIgnoreStart
            // Only executed in thread.
            \define("AMP_CONTEXT", "parallel");
            \define("AMP_CONTEXT_ID", $id);

            EventLoop::unreference(EventLoop::repeat(self::EXIT_CHECK_FREQUENCY, function (): void {
                // Timer to give the chance for the PHP VM to be interrupted by Runtime::kill(), since system calls
                // such as select() will not be interrupted.
            }));

            EventLoop::queue(function () use ($uri, $key, $path, $argv): void {
                try {
                    $socket = IpcHub::connect($uri, $key);
                    $channel = new StreamChannel($socket, $socket);
                } catch (\Throwable $exception) {
                    \trigger_error($exception->getMessage(), E_USER_ERROR);
                }

                try {
                    if (!\is_file($path)) {
                        throw new \Error(\sprintf(
                            "No script found at '%s' (be sure to provide the full path to the script)",
                            $path
                        ));
                    }

                    $argc = \array_unshift($argv, $path);

                    try {
                        // Protect current scope by requiring script within another function.
                        $callable = (function () use (
                            $argc,
                            $argv
                        ): callable { // Using $argc so it is available to the required script.
                            /** @psalm-suppress UnresolvableInclude */
                            return require $argv[0];
                        })->bindTo(new \stdClass)();
                    } catch (\TypeError $exception) {
                        throw new \Error(
                            \sprintf("Script '%s' did not return a callable function", $path),
                            0,
                            $exception
                        );
                    } catch (\ParseError $exception) {
                        throw new \Error(\sprintf("Script '%s' contains a parse error", $path), 0, $exception);
                    }

                    $returnValue = $callable(new Internal\ContextChannel($channel));

                    $result = new Internal\ExitSuccess($returnValue instanceof Future ? $returnValue->await() : $returnValue);
                } catch (\Throwable $exception) {
                    $result = new Internal\ExitFailure($exception);
                }

                try {
                    try {
                        $channel->send($result);
                    } catch (SerializationException $exception) {
                        // Serializing the result failed. Send the reason why.
                        $channel->send(new Internal\ExitFailure($exception));
                    }
                } catch (\Throwable $exception) {
                    \trigger_error(sprintf(
                        "Could not send result to parent: '%s'; be sure to shutdown the child before ending the parent",
                        $exception->getMessage(),
                    ), E_USER_ERROR);
                } finally {
                    $channel->close();
                }
            });

            EventLoop::run();

            return 0;
            // @codeCoverageIgnoreEnd
        }, [
            $id,
            $hub->getUri(),
            $key = $hub->generateKey(),
            $command,
            $args,
        ]);

        try {
            $socket = $hub->accept($key);
            $channel = new StreamChannel($socket, $socket);
            $hub->add($id, $channel, $future);
        } catch (\Throwable $exception) {
            $runtime->kill();
            throw new ContextException("Starting the parallel runtime failed", 0, $exception);
        }

        return new self($id, $runtime, $channel, $hub);
    }

    private function __construct(
        int $id,
        Runtime $runtime,
        StreamChannel $channel,
        private ParallelHub $hub,
    ) {
        $this->oid = \getmypid();
        $this->id = $id;
        $this->runtime = $runtime;
        $this->channel = $channel;
    }

    /**
     * Always throws to prevent cloning.
     */
    public function __clone()
    {
        throw new \Error(self::class . ' objects cannot be cloned');
    }

    /**
     * Kills the thread if it is still running.
     */
    public function __destruct()
    {
        if (\getmypid() === $this->oid) {
            $this->kill();
        }
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning(): bool
    {
        return $this->channel !== null;
    }

    /**
     * Immediately kills the context.
     */
    public function kill(): void
    {
        $this->killed = true;

        if ($this->runtime !== null) {
            try {
                $this->runtime->kill();
            } finally {
                $this->close();
            }
        }
    }

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return TResult
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError Thrown if an exit status object is not received.
     * @throws ContextException If the context stops responding.
     */
    public function join(): mixed
    {
        if ($this->channel === null) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        try {
            $response = $this->channel->receive();
            $this->close();
        } catch (\Throwable $exception) {
            $this->kill();
            throw new ContextException("Failed to receive result from thread", 0, $exception);
        }

        if ($response === null) {
            throw new ContextException("Failed to receive result from thread");
        }

        if (!$response instanceof Internal\ExitResult) {
            $this->kill();
            throw new SynchronizationError('Did not receive an exit result from thread.');
        }

        return $response->getResult();
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        if ($this->channel === null) {
            throw new StatusError('The thread has not been started.');
        }

        try {
            $data = $this->channel->receive($cancellation);
        } catch (ChannelException $e) {
            throw new ContextException(
                "The thread stopped responding, potentially due to a fatal error or calling exit",
                0,
                $e
            );
        }

        if ($data === null) {
            throw new ContextException("The channel closed when receiving data from the thread");
        }

        if ($data instanceof Internal\ExitResult) {
            $data = $data->getResult();
            throw new SynchronizationError(\sprintf(
                'Thread unexpectedly exited with result of type: %s',
                get_debug_type($data),
            ));
        }


        if (!$data instanceof Internal\ContextMessage) {
            throw new SynchronizationError(\sprintf(
                'Unexpected data type from context: %s',
                get_debug_type($data),
            ));
        }

        return $data->getMessage();
    }

    public function send(mixed $data): void
    {
        if ($this->channel === null) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        try {
            $this->channel->send($data);
        } catch (ChannelException $e) {
            if ($this->channel->isClosed()) {
                throw new ContextException(
                    "The thread stopped responding, potentially due to a fatal error or calling exit",
                    0,
                    $e
                );
            }

            try {
                $data = async(fn () => $this->join())->await(new TimeoutCancellation(0.1));
            } catch (ContextException | ChannelException | CancelledException) {
                $this->kill();
                throw new ContextException(
                    "The thread stopped responding, potentially due to a fatal error or calling exit",
                    0,
                    $e
                );
            }

            throw new SynchronizationError(\sprintf(
                'Thread unexpectedly exited with result of type: %s',
                \is_object($data) ? \get_class($data) : \gettype($data)
            ), 0, $e);
        }
    }

    /**
     * Returns the ID of the thread. This ID will be unique to this process.
     *
     * @return int
     *
     * @throws StatusError
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Closes channel and socket if still open.
     */
    public function close(): void
    {
        $this->runtime = null;

        if ($this->channel !== null) {
            $this->channel->close();
        }

        $this->channel = null;

        $this->hub->remove($this->id);
    }

    public function isClosed(): bool
    {
        return $this->channel && $this->channel->isClosed();
    }
}
