<?php
namespace Icicle\Concurrent\Process;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\Internal\ExitStatusInterface;

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
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
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
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        if ($data instanceof ExitStatusInterface) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        yield $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        $response = (yield $this->channel->receive());

        yield $this->process->join();

        if (!$response instanceof ExitStatusInterface) {
            throw new SynchronizationError('Did not receive an exit status from thread.');
        }

        yield $response->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->process->kill();
        $this->channel = null;
    }
}
