<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ForkException;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Concurrent\Sync\Internal\ExitFailure;
use Icicle\Concurrent\Sync\Internal\ExitStatusInterface;
use Icicle\Concurrent\Sync\Internal\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
class Fork implements ContextInterface
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
     * @var mixed[]
     */
    private $args;

    /**
     * Spawns a new forked process and runs it.
     *
     * @param callable $function A callable to invoke in the process.
     *
     * @return \Icicle\Concurrent\Forking\Fork The process object that was spawned.
     */
    public static function spawn(callable $function /* , ...$args */)
    {
        $class  = new \ReflectionClass(__CLASS__);
        $fork = $class->newInstanceArgs(func_get_args());
        $fork->start();
        return $fork;
    }

    public function __construct(callable $function /* , ...$args */)
    {
        $this->function = $function;
        $this->args = array_slice(func_get_args(), 1);
    }

    public function __clone()
    {
        $this->pid = 0;
        $this->channel = null;
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning()
    {
        return 0 !== $this->pid && false !== posix_getpgid($this->pid);
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
     * Gets the fork's scheduling priority as a percentage.
     *
     * The priority is a float between 0 and 1 that indicates the relative priority for the forked process, where 0 is
     * very low priority, 1 is very high priority, and 0.5 is considered a "normal" priority. The value is based on the
     * forked process's "nice" value. The priority affects the operating system's scheduling of processes. How much the
     * priority actually affects the amount of CPU time the process gets is ultimately system-specific.
     *
     * @return float A priority value between 0 and 1.
     *
     * @throws ForkException If the operation failed.
     *
     * @see Fork::setPriority()
     * @see http://linux.die.net/man/2/getpriority
     */
    public function getPriority()
    {
        if (($nice = pcntl_getpriority($this->pid)) === false) {
            throw new ForkException('Failed to get the fork\'s priority.');
        }

        return (19 - $nice) / 39;
    }

    /**
     * Sets the fork's scheduling priority as a percentage.
     *
     * Note that on many systems, only the superuser can increase the priority of a process.
     *
     * @param float $priority A priority value between 0 and 1.
     *
     * @throws InvalidArgumentError If the given priority is an invalid value.
     * @throws ForkException        If the operation failed.
     *
     * @see Fork::getPriority()
     */
    public function setPriority($priority)
    {
        if ($priority < 0 || $priority > 1) {
            throw new InvalidArgumentError('Priority value must be between 0.0 and 1.0.');
        }

        $nice = round(19 - ($priority * 39));

        if (!pcntl_setpriority($nice, $this->pid, PRIO_PROCESS)) {
            throw new ForkException('Failed to set the fork\'s priority.');
        }
    }

    /**
     * Starts the context execution.
     */
    public function start()
    {
        if ($this->channel instanceof ChannelInterface) {
            throw new StatusError('The context has already been started.');
        }

        list($parent, $child) = Channel::createSocketPair();

        switch ($pid = pcntl_fork()) {
            case -1: // Failure
                throw new ForkException('Could not fork process!');

            case 0: // Child
                // We will have a cloned event loop from the parent after forking. The
                // child context by default is synchronous and uses the parent event
                // loop, so we need to stop the clone before doing any work in case it
                // is already running.
                $loop = Loop\loop();
                $loop->stop();
                $loop->reInit();
                $loop->clear();

                $channel = new Channel(new DuplexStream($parent));
                fclose($child);

                $coroutine = new Coroutine($this->execute($channel));
                $coroutine->done();

                try {
                    $loop->run();
                    $code = 0;
                } catch (\Exception $exception) {
                    $code = 1;
                }

                $channel->close();

                exit($code);

            default: // Parent
                $this->pid = $pid;
                $this->channel = new Channel(new DuplexStream($child));
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
        $executor = new Executor($channel);

        try {
            if ($this->function instanceof \Closure) {
                $function = $this->function->bindTo($executor, Executor::class);
            }

            if (empty($function)) {
                $function = $this->function;
            }

            $result = new ExitSuccess(yield call_user_func_array($function, $this->args));
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        yield $channel->send($result);
    }

    /**
     * Immediately kills the context.
     */
    public function kill()
    {
        if ($this->isRunning()) {
            // forcefully kill the process using SIGKILL
            posix_kill($this->pid, SIGKILL);
        }

        if (null !== $this->channel && $this->channel->isOpen()) {
            $this->channel->close();
        }

        $this->pid = 0;
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
     * @throws \Icicle\Concurrent\Exception\StatusError          Thrown if the context has not been started.
     * @throws \Icicle\Concurrent\Exception\SynchronizationError Thrown if an exit status object is not received.
     */
    public function join()
    {
        if (!$this->channel instanceof ChannelInterface) {
            throw new StatusError('The context has not been started.');
        }

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
        if (!$this->channel instanceof ChannelInterface) {
            throw new StatusError('The context has not been started.');
        }

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
        if (!$this->channel instanceof ChannelInterface) {
            throw new StatusError('The context has not been started.');
        }

        if ($data instanceof ExitStatusInterface) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        yield $this->channel->send($data);
    }
}
