<?php
namespace Icicle\Concurrent\Process;

use Icicle\Concurrent\Exception\{StatusError, SynchronizationError};
use Icicle\Concurrent\{Process as ProcessContext, Strand};
use Icicle\Concurrent\Sync\{ChannelledStream, Internal\ExitStatus};
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
    public function __construct(string $path, string $cwd = '', array $env = [])
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
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): \Generator
    {
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        $data = yield from $this->channel->receive();

        if ($data instanceof ExitStatus) {
            $data = $data->getResult();
            throw new SynchronizationError(sprintf(
                'Thread unexpectedly exited with result of type: %s',
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): \Generator
    {
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        if ($data instanceof ExitStatus) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        return yield from $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function join(): \Generator
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
    public function getPid(): int
    {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signo)
    {
        $this->process->signal($signo);
    }
}
