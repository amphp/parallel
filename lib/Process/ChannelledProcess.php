<?php declare(strict_types = 1);

namespace Amp\Parallel\Process;

use Amp\Parallel\{ Process as ProcessContext, StatusError, Strand, SynchronizationError };
use Amp\Parallel\Sync\{ ChannelledStream, Internal\ExitStatus };
use Interop\Async\Awaitable;

class ChannelledProcess implements ProcessContext, Strand {
    /**
     * @var \Amp\Parallel\Process\Process
     */
    private $process;

    /**
     * @var \Amp\Parallel\Sync\Channel
     */
    private $channel;

    /**
     * @param string $path Path to PHP script.
     * @param string $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     */
    public function __construct(string $path, string $cwd = '', array $env = []) {
        $command = \PHP_BINARY . ' ' . $path;
        $this->process = new Process($command, $cwd, $env);
    }

    /**
     * Resets process values.
     */
    public function __clone() {
        $this->process = clone $this->process;
        $this->channel = null;
    }

    /**
     * {@inheritdoc}
     */
    public function start() {
        $this->process->start();
        $this->channel = new ChannelledStream($this->process->getStdOut(), $this->process->getStdIn());
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Awaitable {
        if ($this->channel === null) {
            throw new StatusError('The process has not been started.');
        }

        return \Amp\pipe($this->channel->receive(), static function ($data) {
            if ($data instanceof ExitStatus) {
                $data = $data->getResult();
                throw new SynchronizationError(\sprintf(
                    'Thread unexpectedly exited with result of type: %s',
                    \is_object($data) ? \get_class($data) : \gettype($data)
                ));
            }
            
            return $data;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Awaitable {
        if ($this->channel === null) {
            throw new StatusError('The process has not been started.');
        }

        if ($data instanceof ExitStatus) {
            throw new \Error('Cannot send exit status objects.');
        }

        return $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function join(): Awaitable {
        return $this->process->join();
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        $this->process->kill();
    }

    /**
     * {@inheritdoc}
     */
    public function getPid(): int {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signo) {
        $this->process->signal($signo);
    }
}
