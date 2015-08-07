<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ForkException;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitInterface;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
class ForkContext extends Synchronized implements ContextInterface
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

    public function __construct(callable $function /* , ...$args */)
    {
        parent::__construct();

        $this->function = $function;
        $this->args = array_slice(func_get_args(), 1);
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
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return posix_getpgid($this->pid) !== false;
    }

    /**
     * {@inheritdoc}
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

                // Execute the context runnable and send the parent context the result.
                try {
                    Promise\wait(new Coroutine($this->execute($channel)));
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
     * @param Channel $channel
     *
     * @return \Generator
     */
    private function execute(Channel $channel)
    {
        try {
            $function = $this->function;
            if ($function instanceof \Closure) {
                $function = $function->bindTo($channel, Channel::class);
            }

            $result = new ExitSuccess(yield call_user_func_array($function, $this->args));
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        yield $channel->send($result);

        $channel->close();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        if ($this->isRunning()) {
            // forcefully kill the process using SIGKILL
            posix_kill($this->getPid(), SIGKILL);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        try {
            $response = (yield $this->channel->receive());

            if (!$response instanceof ExitInterface) {
                throw new SynchronizationError('Did not receive an exit status from fork.');
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

        if ($data instanceof ExitInterface) {
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
        return $this->channel->send($data);
    }
}
