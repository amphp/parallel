<?php
namespace Icicle\Concurrent\Process;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;

class ChannelledProcess implements ContextInterface
{
    /**
     * @var \Icicle\Concurrent\Process\Process
     */
    private $process;

    /**
     * @var \Icicle\Concurrent\Sync\Channel
     */
    private $channel;

    /**
     * @param string $path Path to PHP script.
     * @param string $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     */
    public function __construct($path, $cwd = '', array $env = [])
    {
        $command = PHP_BINARY . ' ' . $path;

        $this->process = new Process($command, $cwd, $env);
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->process->start();

        $this->channel = new Channel($this->process->getStdOut(), $this->process->getStdIn());
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
    public function receive()
    {
        if (!$this->channel instanceof ChannelInterface) {
            throw new SynchronizationError('The process has not been started.');
        }

        yield $this->channel->receive();
    }

    /**
     * {@inheritdoc}
     */
    public function send($data)
    {
        if (!$this->channel instanceof ChannelInterface) {
            throw new SynchronizationError('The process has not been started.');
        }

        yield $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        return $this->process->join();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->process->kill();
    }

    public function error()
    {

    }
}
