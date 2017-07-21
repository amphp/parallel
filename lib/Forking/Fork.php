<?php

namespace Amp\Parallel\Forking;

use Amp\Coroutine;
use Amp\Loop;
use Amp\Parallel\ContextException;
use Amp\Parallel\Process;
use Amp\Parallel\StatusError;
use Amp\Parallel\Strand;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Parallel\Sync\Internal\ExitFailure;
use Amp\Parallel\Sync\Internal\ExitResult;
use Amp\Parallel\Sync\Internal\ExitSuccess;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\SynchronizationError;
use Amp\Promise;
use function Amp\call;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
class Fork implements Process, Strand {
    /** @var \Amp\Parallel\Sync\ChannelledSocket A channel for communicating with the child. */
    private $channel;

    /** @var int */
    private $pid = 0;

    /** @var callable */
    private $function;

    /** @var mixed[] */
    private $args;

    /** @var int */
    private $oid = 0;

    /**
     * Checks if forking is enabled.
     *
     * @return bool True if forking is enabled, otherwise false.
     */
    public static function supported(): bool {
        return \extension_loaded('pcntl');
    }

    /**
     * Spawns a new forked process and runs it.
     *
     * @param callable $function A callable to invoke in the process.
     *
     * @return \Amp\Parallel\Forking\Fork The process object that was spawned.
     */
    public static function spawn(callable $function, ...$args): self {
        $fork = new self($function, ...$args);
        $fork->start();
        return $fork;
    }

    public function __construct(callable $function, ...$args) {
        if (!self::supported()) {
            throw new \Error("The pcntl extension is required to create forks.");
        }

        $this->function = $function;
        $this->args = $args;
    }

    public function __clone() {
        $this->pid = 0;
        $this->oid = 0;
        $this->channel = null;
    }

    public function __destruct() {
        if (0 !== $this->pid && \posix_getpid() === $this->oid) { // Only kill in owner process.
            $this->kill(); // Will only terminate if the process is still running.
        }
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning(): bool {
        return 0 !== $this->pid && false !== \posix_getpgid($this->pid);
    }

    /**
     * Gets the forked process's process ID.
     *
     * @return int The process ID.
     */
    public function getPid(): int {
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
     * @throws ContextException If the operation failed.
     *
     * @see Fork::setPriority()
     * @see http://linux.die.net/man/2/getpriority
     */
    public function getPriority(): float {
        if (($nice = \pcntl_getpriority($this->pid)) === false) {
            throw new ContextException('Failed to get the fork\'s priority.');
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
     * @throws \Error If the given priority is an invalid value.
     * @throws ContextException If the operation failed.
     *
     * @see Fork::getPriority()
     */
    public function setPriority(float $priority) {
        if ($priority < 0 || $priority > 1) {
            throw new \Error('Priority value must be between 0.0 and 1.0.');
        }

        $nice = (int) \round(19 - ($priority * 39));

        if (!\pcntl_setpriority($nice, $this->pid, \PRIO_PROCESS)) {
            throw new ContextException('Failed to set the fork\'s priority.');
        }
    }

    /**
     * Starts the context execution.
     *
     * @throws \Amp\Parallel\ContextException If forking fails.
     */
    public function start() {
        if (0 !== $this->oid) {
            throw new StatusError('The context has already been started.');
        }

        $sockets = @\stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            $message = "Failed to create socket pair";
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            throw new ContextException($message);
        }

        list($parent, $child) = $sockets;

        switch ($pid = \pcntl_fork()) {
            case -1: // Failure
                throw new ContextException('Could not fork process!');

            case 0: // Child
                // @codeCoverageIgnoreStart
                \fclose($child);

                Loop::set((new Loop\DriverFactory)->create()); // Replace loop instance inherited from parent.
                Loop::run(function () use ($parent) {
                    return $this->execute(new ChannelledSocket($parent, $parent));
                });

                exit(0);
                // @codeCoverageIgnoreEnd
            default: // Parent
                $this->pid = $pid;
                $this->oid = \posix_getpid();
                $this->channel = new ChannelledSocket($child, $child);
                \fclose($parent);
        }
    }

    /**
     * @coroutine
     *
     * This method is run only on the child.
     *
     * @param \Amp\Parallel\Sync\Channel $channel
     *
     * @return \Generator
     *
     * @codeCoverageIgnore Only executed in the child.
     */
    private function execute(Channel $channel): \Generator {
        try {
            if ($this->function instanceof \Closure) {
                $result = call($this->function->bindTo($channel, null), ...$this->args);
            } else {
                $result = call($this->function, ...$this->args);
            }

            $result = new ExitSuccess(yield $result);
        } catch (\Throwable $exception) {
            $result = new ExitFailure($exception);
        }

        // Attempt to return the result.
        try {
            try {
                yield $channel->send($result);
            } catch (SerializationException $exception) {
                // Serializing the result failed. Send the reason why.
                yield $channel->send(new ExitFailure($exception));
            }
        } catch (ChannelException $exception) {
            // The result was not sendable! The parent context must have died or killed the context.
        }
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        if ($this->isRunning()) {
            // Forcefully kill the process using SIGKILL.
            \posix_kill($this->pid, \SIGKILL);
        }

        if ($this->channel !== null) {
            $this->channel->close();
        }

        // "Detach" from the process and let it die asynchronously.
        $this->pid = 0;
        $this->channel = null;
    }

    /**
     * @param int $signo
     *
     * @throws \Amp\Parallel\StatusError
     */
    public function signal(int $signo) {
        if (0 === $this->pid) {
            throw new StatusError('The fork has not been started or has already finished.');
        }

        \posix_kill($this->pid, $signo);
    }

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return \Amp\Promise<int>
     *
     * @throws \Amp\Parallel\StatusError          Thrown if the context has not been started.
     * @throws \Amp\Parallel\SynchronizationError Thrown if an exit status object is not received.
     */
    public function join(): Promise {
        if (null === $this->channel) {
            throw new StatusError('The fork has not been started or has already finished.');
        }

        return new Coroutine($this->doJoin());
    }

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @throws \Amp\Parallel\SynchronizationError
     */
    private function doJoin(): \Generator {
        try {
            $response = yield $this->channel->receive();

            if (!$response instanceof ExitResult) {
                throw new SynchronizationError(\sprintf(
                    'Did not receive an exit result from fork. Instead received data of type %s',
                    \is_object($response) ? \get_class($response) : \gettype($response)
                ));
            }
        } catch (ChannelException $e) {
            throw new ContextException("The context stopped responding, potentially due to a fatal error or calling exit", 0, $e);
        } finally {
            $this->kill();
        }

        return $response->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Promise {
        if (null === $this->channel) {
            throw new StatusError('The process has not been started.');
        }

        return new Coroutine($this->doReceive());
    }

    private function doReceive() {
        try {
            $data = yield $this->channel->receive();
        } catch (ChannelException $e) {
            throw new ContextException("The context stopped responding, potentially due to a fatal error or calling exit", 0, $e);
        }

        if ($data instanceof ExitResult) {
            $data = $data->getResult();
            throw new SynchronizationError(\sprintf(
                'Forked process unexpectedly exited with result of type: %s',
                \is_object($data) ? \get_class($data) : \gettype($data)
            ));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Promise {
        if (null === $this->channel) {
            throw new StatusError('The fork has not been started or has already finished.');
        }

        if ($data instanceof ExitResult) {
            throw new \Error('Cannot send exit result objects.');
        }

        return call(function () use ($data) {
            try {
                yield $this->channel->send($data);
            } catch (ChannelException $e) {
                throw new ContextException("The context went away, potentially due to a fatal error or calling exit", 0, $e);
            }
        });
    }
}
