<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Exception\{StatusError, SynchronizationError, ThreadException};
use Icicle\Concurrent\Strand;
use Icicle\Concurrent\Sync\{ChannelledStream, Internal\ExitStatus};
use Icicle\Coroutine;
use Icicle\Exception\{InvalidArgumentError, UnsupportedError};
use Icicle\Stream;
use Icicle\Stream\Pipe\DuplexPipe;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class Thread implements Strand
{
    /**
     * @var Internal\Thread An internal thread instance.
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\Sync\Channel A channel for communicating with the thread.
     */
    private $channel;

    /**
     * @var \Icicle\Stream\Pipe\DuplexPipe
     */
    private $pipe;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var callable
     */
    private $function;

    /**
     * @var mixed[]
     */
    private $args;

    /**
     * @var int
     */
    private $oid = 0;

    /**
     * Checks if threading is enabled.
     *
     * @return bool True if threading is enabled, otherwise false.
     */
    public static function enabled(): bool
    {
        return extension_loaded('pthreads');
    }

    /**
     * Spawns a new thread and runs it.
     *
     * @param callable $function The callable to invoke in the thread.
     *
     * @return Thread The thread object that was spawned.
     */
    public static function spawn(callable $function, ...$args)
    {
        $thread = new self($function, ...$args);
        $thread->start();
        return $thread;
    }

    /**
     * Creates a new thread.
     *
     * @param callable $function The callable to invoke in the thread when run.
     *
     * @throws InvalidArgumentError If the given function cannot be safely invoked in a thread.
     * @throws UnsupportedError Thrown if the pthreads extension is not available.
     */
    public function __construct(callable $function, ...$args)
    {
        if (!self::enabled()) {
            throw new UnsupportedError("The pthreads extension is required to create threads.");
        }

        // Make sure closures don't `use` other variables or have statics.
        if ($function instanceof \Closure) {
            $reflector = new \ReflectionFunction($function);
            if (!empty($reflector->getStaticVariables())) {
                throw new InvalidArgumentError('Closures with static variables cannot be passed to thread.');
            }
        }

        $this->function = $function;
        $this->args = $args;
    }

    /**
     * Returns the thread to the condition before starting. The new thread can be started and run independently of the
     * first thread.
     */
    public function __clone()
    {
        $this->thread = null;
        $this->socket = null;
        $this->pipe = null;
        $this->channel = null;
        $this->oid = 0;
    }

    /**
     * Kills the thread if it is still running.
     *
     * @throws \Icicle\Concurrent\Exception\ThreadException
     */
    public function __destruct()
    {
        if (getmypid() === $this->oid) {
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
        return null !== $this->thread && $this->thread->isRunning() && null !== $this->pipe && $this->pipe->isOpen();
    }

    /**
     * Spawns the thread and begins the thread's execution.
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the thread has already been started.
     * @throws \Icicle\Concurrent\Exception\ThreadException If starting the thread was unsuccessful.
     * @throws \Icicle\Stream\Exception\FailureException If creating a socket pair fails.
     */
    public function start()
    {
        if (0 !== $this->oid) {
            throw new StatusError('The thread has already been started.');
        }

        $this->oid = getmypid();

        list($channel, $this->socket) = Stream\pair();

        $this->thread = new Internal\Thread($this->socket, $this->function, $this->args);

        if (!$this->thread->start(PTHREADS_INHERIT_INI | PTHREADS_INHERIT_FUNCTIONS | PTHREADS_INHERIT_CLASSES)) {
            throw new ThreadException('Failed to start the thread.');
        }

        $this->channel = new ChannelledStream($this->pipe = new DuplexPipe($channel));
    }

    /**
     * Immediately kills the context.
     *
     * @throws ThreadException If killing the thread was unsuccessful.
     */
    public function kill()
    {
        if (null !== $this->thread) {
            try {
                if ($this->thread->isRunning() && !$this->thread->kill()) {
                    throw new ThreadException('Could not kill thread.');
                }
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
        if (null !== $this->pipe && $this->pipe->isOpen()) {
            $this->pipe->close();
        }

        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->thread = null;
        $this->channel = null;
    }

    /**
     * @coroutine
     *
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return \Generator
     *
     * @resolve mixed Resolved with the return or resolution value of the context once it has completed execution.
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError Thrown if an exit status object is not received.
     */
    public function join(): \Generator
    {
        if (null === $this->channel || null === $this->thread) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        try {
            $response = yield from $this->channel->receive();

            if (!$response instanceof ExitStatus) {
                throw new SynchronizationError('Did not receive an exit status from thread.');
            }

            $result = $response->getResult();

            $this->thread->join();
        } catch (\Throwable $exception) {
            $this->kill();
            throw $exception;
        }

        $this->close();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): \Generator
    {
        if (null === $this->channel) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        $data = yield from $this->channel->receive();

        if ($data instanceof ExitStatus) {
            $this->kill();
            $data = $data->getResult();
            throw new SynchronizationError(sprintf(
                'Thread unexpectedly exited with result of type: %s',
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): \Generator
    {
        if (null === $this->channel) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        if ($data instanceof ExitStatus) {
            $this->kill();
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        return yield from $this->channel->send($data);
    }
}
