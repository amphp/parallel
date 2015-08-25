<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ChannelInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitStatusInterface;
use Icicle\Concurrent\Sync\Lock;
use Icicle\Coroutine;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class Thread implements ChannelInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var \Icicle\Concurrent\Threading\InternalThread An internal thread instance.
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\Sync\Channel A channel for communicating with the thread.
     */
    private $channel;

    /**
     * @var resource
     */
    private $socket;

    /**
     * Spawns a new thread and runs it.
     *
     * @param callable $function A callable to invoke in the thread.
     *
     * @return \Icicle\Concurrent\Threading\Thread The thread object that was spawned.
     */
    public static function spawn(callable $function /* , ...$args */)
    {
        $class  = new \ReflectionClass(__CLASS__);
        $thread = $class->newInstanceArgs(func_get_args());
        $thread->start();
        return $thread;
    }

    /**
     * Creates a new thread context from a thread.
     *
     * @param callable $function
     */
    public function __construct(callable $function /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        list($channel, $this->socket) = Channel::createSocketPair();

        $this->thread = new InternalThread($this->socket, $function, $args);
        $this->channel = new Channel(new DuplexStream($channel));
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning()
    {
        return $this->thread->isRunning();
    }

    /**
     * Starts the context execution.
     */
    public function start()
    {
        if ($this->isRunning()) {
            throw new SynchronizationError('The thread has already been started.');
        }

        $this->thread->start(PTHREADS_INHERIT_ALL);
    }

    /**
     * Immediately kills the context.
     */
    public function kill()
    {
        $this->channel->close();
        $this->thread->kill();
        fclose($this->socket);
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
     * @throws \Icicle\Concurrent\Exception\SynchronizationError Thrown if an exit status object is not received.
     */
    public function join()
    {
        if (!$this->isRunning()) {
            throw new SynchronizationError('The thread has not been started or has already finished.');
        }

        try {
            $response = (yield $this->channel->receive());

            if (!$response instanceof ExitStatusInterface) {
                throw new SynchronizationError('Did not receive an exit status from thread.');
            }

            yield $response->getResult();
        } finally {
            $this->thread->join();
            $this->channel->close();
            fclose($this->socket);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        if (!$this->isRunning()) {
            throw new SynchronizationError('The thread has not been started or has already finished.');
        }

        $data = (yield $this->channel->receive());

        if ($data instanceof ExitStatusInterface) {
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
        if (!$this->isRunning()) {
            throw new SynchronizationError('The thread has not been started or has already finished.');
        }

        if ($data instanceof ExitStatusInterface) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        return $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        while (!$this->thread->tsl()) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }

        yield new Lock(function () {
            $this->thread->release();
        });
    }
}
