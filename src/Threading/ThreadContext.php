<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitStatusInterface;
use Icicle\Concurrent\Sync\Lock;
use Icicle\Coroutine;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class ThreadContext implements ContextInterface
{
    /**
     * @var \Icicle\Concurrent\Threading\Thread A thread instance.
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\Sync\Channel A channel for communicating with the thread.
     */
    private $channel;

    /**
     * Spawns a new thread and runs it.
     *
     * @param callable $function A callable to invoke in the thread.
     *
     * @return ThreadContext The thread object that was spawned.
     */
    public static function spawn(callable $function /* , ...$args */)
    {
        $thread = new static($function);
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

        list($channel, $socket) = Channel::createSocketPair();

        $this->channel = new Channel($channel);
        $this->thread = new Thread($socket, $function, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->thread->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->isRunning()) {
            throw new SynchronizationError('The thread has already been started.');
        }

        $this->thread->start(PTHREADS_INHERIT_ALL);
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->channel->close();
        $this->thread->kill();
    }

    /**
     * {@inheritdoc}
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
            $this->channel->close();
            $this->thread->join();
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
            yield Coroutine\sleep(0.01);
        }

        yield new Lock(function () {
            $this->thread->release();
        });
    }
}
