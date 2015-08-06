<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
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
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

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

    public function __construct(callable $function)
    {
        parent::__construct();

        $this->function = $function;
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

        if (($pid = pcntl_fork()) === -1) {
            throw new \Exception();
        }

        // We are the parent inside this block.
        if ($pid !== 0) {
            $this->channel = new Channel($parent);
            fclose($child);

            $this->pid = $pid;

            return;
        }

        $channel = new Channel($child);
        fclose($parent);

        // We will have a cloned event loop from the parent after forking. The
        // child context by default is synchronous and uses the parent event
        // loop, so we need to stop the clone before doing any work in case it
        // is already running.
        Loop\stop();
        Loop\reInit();
        Loop\clear();

        // Execute the context runnable and send the parent context the result.
        Promise\wait(new Coroutine($this->execute($channel)));

        exit(0);
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
            $result = new ExitSuccess(yield $function($channel));
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
            throw new SynchronizationError(sprintf('Fork exited with result of type: %s', $data->getResult()));
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
