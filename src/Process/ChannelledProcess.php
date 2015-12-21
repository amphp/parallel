<?php
namespace Icicle\Concurrent\Process;

use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Process as ProcessContext;
use Icicle\Concurrent\Strand;
use Icicle\Concurrent\Sync\ChannelledStream;
use Icicle\Concurrent\Sync\Internal\ExitStatus;
use Icicle\Exception\InvalidArgumentError;

class ChannelledProcess implements ProcessContext, Strand
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
     * Resets process values.
     */
    public function __clone()
    {
        $this->process = clone $this->process;
        $this->channel = null;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->process->start();

        $this->channel = new ChannelledStream($this->process->getStdOut(), $this->process->getStdIn());
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
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        $data = (yield $this->channel->receive());

        if ($data instanceof ExitStatus) {
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
            throw new StatusError('The process has not been started.');
        }

        if ($data instanceof ExitStatus) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
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

    /**
     * {@inheritdoc}
     */
    public function getPid()
    {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function signal($signo)
    {
        $this->process->signal($signo);
    }
}
