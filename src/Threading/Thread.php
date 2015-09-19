<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Exception\ThreadException;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\Internal\ExitStatusInterface;
use Icicle\Coroutine;
use Icicle\Socket;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class Thread implements ContextInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var Internal\Thread An internal thread instance.
     */
    private $thread;

    /**
     * @var Channel A channel for communicating with the thread.
     */
    private $channel;

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
     * Spawns a new thread and runs it.
     *
     * @param callable $function The callable to invoke in the thread.
     *
     * @return Thread The thread object that was spawned.
     */
    public static function spawn(callable $function /* , ...$args */)
    {
        $class  = new \ReflectionClass(__CLASS__);
        $thread = $class->newInstanceArgs(func_get_args());
        $thread->start();
        return $thread;
    }

    /**
     * Creates a new thread.
     *
     * @param callable $function The callable to invoke in the thread when run.
     *
     * @throws InvalidArgumentError If the given function cannot be safely invoked in a thread.
     */
    public function __construct(callable $function /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

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
    public function isRunning()
    {
        return null !== $this->thread && $this->thread->isRunning() && $this->channel->isOpen();
    }

    /**
     * Spawns the thread and begins the thread's execution.
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the thread has already been started.
     * @throws \Icicle\Concurrent\Exception\ThreadException If starting the thread was unsuccessful.
     * @throws \Icicle\Socket\Exception\FailureException If creating a socket pair fails.
     */
    public function start()
    {
        if (0 !== $this->oid) {
            throw new StatusError('The thread has already been started.');
        }

        $this->oid = getmypid();

        list($channel, $this->socket) = Socket\pair();

        $this->thread = new Internal\Thread($this->socket, $this->function, $this->args);

        if (!$this->thread->start(PTHREADS_INHERIT_INI | PTHREADS_INHERIT_FUNCTIONS | PTHREADS_INHERIT_CLASSES)) {
            throw new ThreadException('Failed to start the thread.');
        }

        $this->channel = new Channel(new DuplexStream($channel));
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
        if (null !== $this->channel && $this->channel->isOpen()) {
            $this->channel->close();
        }

        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->thread = null;
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
    public function join()
    {
        if (null === $this->channel || null === $this->thread) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        try {
            $response = (yield $this->channel->receive());

            if (!$response instanceof ExitStatusInterface) {
                throw new SynchronizationError('Did not receive an exit status from thread.');
            }

            yield $response->getResult();

            $this->thread->join();
        } catch (\Exception $exception) {
            $this->kill();
            throw $exception;
        }

        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        if (null === $this->channel) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        $data = (yield $this->channel->receive());

        if ($data instanceof ExitStatusInterface) {
            $this->kill();
            $data = $data->getResult();
            throw new SynchronizationError(sprintf(
                'Thread unexpectedly exited with result of type: %s',
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        yield $data;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data)
    {
        if (null === $this->channel) {
            throw new StatusError('The thread has not been started or has already finished.');
        }

        if ($data instanceof ExitStatusInterface) {
            $this->kill();
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        yield $this->channel->send($data);
    }
}
