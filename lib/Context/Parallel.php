<?php

namespace Amp\Parallel\Context;

use Amp\Failure;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Parallel\Sync\ExitResult;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\Promise;
use parallel\Exception as ParallelException;
use parallel\Runtime;
use function Amp\call;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
final class Parallel implements Context
{
    const KEY_LENGTH = 32;

    /** @var string|null */
    private static $autoloadPath;

    /** @var Internal\ProcessHub */
    private $hub;

    /** @var Runtime|null */
    private $runtime;

    /** @var ChannelledSocket|null A channel for communicating with the parallel thread. */
    private $channel;

    /** @var string Script path. */
    private $script;

    /** @var mixed[] */
    private $args;

    /** @var int */
    private $oid = 0;

    /** @var \parallel\Future|null */
    private $future;

    /**
     * Checks if threading is enabled.
     *
     * @return bool True if threading is enabled, otherwise false.
     */
    public static function isSupported(): bool
    {
        return \extension_loaded('parallel');
    }

    /**
     * Creates and starts a new thread.
     *
     * @param callable $function The callable to invoke in the thread. First argument is an instance of
     *     \Amp\Parallel\Sync\Channel.
     * @param mixed ...$args Additional arguments to pass to the given callable.
     *
     * @return Promise<Thread> The thread object that was spawned.
     */
    public static function run(string $path, ...$args): Promise
    {
        $thread = new self($path, ...$args);
        return call(function () use ($thread) {
            yield $thread->start();
            return $thread;
        });
    }

    /**
     * Creates a new thread.
     *
     * @param callable $function The callable to invoke in the thread. First argument is an instance of
     *     \Amp\Parallel\Sync\Channel.
     * @param mixed ...$args Additional arguments to pass to the given callable.
     *
     * @throws \Error Thrown if the pthreads extension is not available.
     */
    public function __construct(string $script, ...$args)
    {
        $this->hub = Loop::getState(self::class);
        if (!$this->hub instanceof Internal\ProcessHub) {
            $this->hub = new Internal\ProcessHub;
            Loop::setState(self::class, $this->hub);
        }

        if (!self::isSupported()) {
            throw new \Error("The parallel extension is required to create parallel threads.");
        }

        $this->script = $script;
        $this->args = $args;

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
    }

    /**
     * Returns the thread to the condition before starting. The new thread can be started and run independently of the
     * first thread.
     */
    public function __clone()
    {
        $this->runtime = null;
        $this->channel = null;
        $this->oid = 0;
    }

    /**
     * Kills the thread if it is still running.
     *
     * @throws \Amp\Parallel\Context\ContextException
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
     * Spawns the thread and begins the thread's execution.
     *
     * @return Promise<null> Resolved once the thread has started.
     *
     * @throws \Amp\Parallel\Context\StatusError If the thread has already been started.
     * @throws \Amp\Parallel\Context\ContextException If starting the thread was unsuccessful.
     */
    public function start(): Promise
    {
        if ($this->oid !== 0) {
            throw new StatusError('The thread has already been started.');
        }

        try {
            $arguments = \serialize($this->args);
        } catch (\Throwable $exception) {
            return new Failure(new SerializationException("Arguments must be serializable.", 0, $exception));
        }

        $this->oid = \getmypid();

        $this->runtime = new Runtime(self::$autoloadPath);

        $id = \random_int(0, \PHP_INT_MAX);

        $this->future = $this->runtime->run(static function (string $uri, string $key, string $path, string $arguments): int {
            \define("AMP_CONTEXT", "parallel");

            if (!$socket = \stream_socket_client($uri, $errno, $errstr, 5, \STREAM_CLIENT_CONNECT)) {
                \trigger_error("Could not connect to IPC socket", E_USER_ERROR);
                return 1;
            }

            $channel = new ChannelledSocket($socket, $socket);

            try {
                Promise\wait($channel->send($key));
            } catch (\Throwable $exception) {
                \trigger_error("Could not send key to parent", E_USER_ERROR);
                return 1;
            }

            return Internal\ParallelRunner::execute($channel, $path, $arguments);
        }, [
            $this->hub->getUri(),
            $this->hub->generateKey($id, self::KEY_LENGTH),
            $this->script,
            $arguments
        ]);

        return call(function () use ($id) {
            try {
                $this->channel = yield $this->hub->accept($id);
            } catch (\Throwable $exception) {
                $this->kill();
                throw new ContextException("Starting the parallel runtime failed", 0, $exception);
            }
        });
    }

    /**
     * Immediately kills the context.
     */
    public function kill()
    {
        if ($this->runtime !== null) {
            try {
                $this->runtime->kill();
            } catch (ParallelException $exception) {
                // Ignore runtime being unusable since we're killing it anyway.
            } finally {
                $this->close();
            }
        }
    }

    /**
     * Closes channel and socket if still open.
     */
    private function close()
    {
        if ($this->channel !== null) {
            $this->channel->close();
        }

        $this->channel = null;
    }

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return \Amp\Promise<mixed>
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError Thrown if an exit status object is not received.
     * @throws ContextException If the context stops responding.
     */
    public function join(): Promise
    {
        if ($this->channel == null || $this->runtime === null || $this->future === null) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        return call(function () {
            try {
                $response = yield $this->channel->receive();

                if (!$response instanceof ExitResult) {
                    throw new SynchronizationError('Did not receive an exit result from thread.');
                }
            } catch (ChannelException $exception) {
                $this->kill();
                throw new ContextException(
                    "The context stopped responding, potentially due to a fatal error or calling exit",
                    0,
                    $exception
                );
            } catch (\Throwable $exception) {
                $this->kill();
                throw $exception;
            } finally {
                $this->close();
            }

            return $response->getResult();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Promise
    {
        if ($this->channel === null) {
            throw new StatusError('The process has not been started.');
        }

        return call(function () {
            $data = yield $this->channel->receive();

            if ($data instanceof ExitResult) {
                $data = $data->getResult();
                throw new SynchronizationError(\sprintf(
                    'Thread process unexpectedly exited with result of type: %s',
                    \is_object($data) ? \get_class($data) : \gettype($data)
                ));
            }

            return $data;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Promise
    {
        if ($this->channel === null) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        if ($data instanceof ExitResult) {
            throw new \Error('Cannot send exit result objects.');
        }

        return $this->channel->send($data);
    }
}
