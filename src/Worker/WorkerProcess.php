<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Process\Process;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Coroutine\Coroutine;
use Icicle\Socket\Stream\ReadableStream;

class WorkerProcess implements WorkerInterface
{
    private $process;

    private $channel;

    public function __construct()
    {
        $this->process = new Process(sprintf('%s %s/process.php', PHP_BINARY, __DIR__), __DIR__);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->process->start();

        $this->channel = new Channel(
            $this->process->getStdOut(),
            $this->process->getStdIn()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->process->kill();
    }

    /**
     * @return \Generator
     */
    public function join()
    {
        return $this->process->join();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        yield $this->channel->send(1);
        yield $this->process->join();
        //var_dump('SENT SHUTDOWN');
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(TaskInterface $task)
    {
        if (!$this->channel instanceof ChannelInterface) {
            throw new SynchronizationError('Worker has not been started.');
        }

        yield $this->channel->send($task);

        yield $this->channel->receive();
    }
}