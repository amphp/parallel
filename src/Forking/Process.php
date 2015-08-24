<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ChannelInterface as ContextChannelInterface;
use Icicle\Concurrent\Exception\ForkException;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitStatusInterface;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
class Process implements ContextChannelInterface
{
    /**
     * @var \Icicle\Concurrent\Sync\Channel A channel for communicating with the child.
     */
    private $channel;

    /**
     * @var int
     */
    private $pid = 0;

    /**
     * @var callable
     */
    private $function;

    /**
     * @var \Icicle\Concurrent\Forking\Synchronized
     */
    private $synchronized;

    /**
     * Spawns a new forked process and runs it.
     *
     * @param callable $function A callable to invoke in the process.
     *
     * @return Thread The process object that was spawned.
     */
    public static function spawn(callable $function /* , ...$args */)
    {
        $thread = new static($function);
        $thread->start();
        return $thread;
    }

    public function __construct(callable $function /* , ...$args */)
    {
        $this->function = $function;
        $this->args = array_slice(func_get_args(), 1);

        $this->synchronized = new Synchronized();
    }

    /**
     * Gets the forked process's process ID.
     *
     * @return int The process ID.
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning()
    {
        return posix_getpgid($this->pid) !== false;
    }

    /**
     * Starts the context execution.
     */
    public function start()
    {
        list($parent, $child) = Channel::createSocketPair();

        switch ($pid = pcntl_fork()) {
            case -1: // Failure
                throw new ForkException('Could not fork process!');

            case 0: // Child
                // We will have a cloned event loop from the parent after forking. The
                // child context by default is synchronous and uses the parent event
                // loop, so we need to stop the clone before doing any work in case it
                // is already running.
                Loop\stop();
                Loop\reInit();
                Loop\clear();

                $channel = new Channel($parent);
                fclose($child);

                $coroutine = new Coroutine($this->execute($channel));
                $coroutine->done();

                try {
                    Loop\run();
                } catch (\Exception $exception) {
                    exit(-1);
                }

                exit(0);

            default: // Parent
                $this->pid = $pid;
                $this->channel = new Channel($child);
                fclose($parent);
        }
    }

    /**
     * @coroutine
     *
     * This method is run only on the child.
     *
     * @param \Icicle\Concurrent\Sync\ChannelInterface $channel
     *
     * @return \Generator
     */
    private function execute(ChannelInterface $channel)
    {
        $executor = new ForkExecutor($this->synchronized, $channel);

        try {
            $function = $this->function;
            if ($function instanceof \Closure) {
                $function = $function->bindTo($executor, ForkExecutor::class);
            }

            $result = new ExitSuccess(yield call_user_func_array($function, $this->args));
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        try {
            yield $channel->send($result);
        } finally {
            $channel->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lock()
    {
        return $this->synchronized->lock();
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        return $this->synchronized->unlock();
    }

    /**
     * {@inheritdoc}
     */
    public function synchronized(callable $callback)
    {
        return $this->synchronized->synchronized($callback);
    }

    /**
     * Immediately kills the context.
     */
    public function kill()
    {
        if ($this->isRunning()) {
            // forcefully kill the process using SIGKILL
            posix_kill($this->getPid(), SIGKILL);

            if (null !== $this->channel && $this->channel->isOpen()) {
                $this->channel->close();
            }
        }
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
     */
    public function join()
    {
        try {
            $response = (yield $this->channel->receive());

            if (!$response instanceof ExitStatusInterface) {
                throw new SynchronizationError(sprintf(
                    'Did not receive an exit status from fork. Instead received data of type %s',
                    is_object($response) ? get_class($response) : gettype($response)
                ));
            }

            yield $response->getResult();
        } finally {
            $this->kill();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        $data = (yield $this->channel->receive());

        if ($data instanceof ExitStatusInterface) {
            $data = $data->getResult();
            throw new SynchronizationError(sprintf(
                'Fork unexpectedly exited with result of type: %s',
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
        if ($data instanceof ExitStatusInterface) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        yield $this->channel->send($data);
    }
}
