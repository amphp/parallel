<?php declare(strict_types = 1);

namespace Amp\Concurrent\Threading;

use Amp\Concurrent\{ContextException, StatusError, SynchronizationError, Strand};
use Amp\Concurrent\Sync\{ChannelledStream, Internal\ExitStatus};
use Amp\Coroutine;
use Amp\Socket\Socket;
use Interop\Async\Awaitable;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class Thread implements Strand {
    /**
     * @var Internal\Thread An internal thread instance.
     */
    private $thread;

    /**
     * @var \Amp\Concurrent\Sync\Channel A channel for communicating with the thread.
     */
    private $channel;

    /**
     * @var \Amp\Socket\Socket
     */
    private $pipe;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var callable
     */
    private $function;

    /**
     * @var mixed[]
     */
    private $args;

    /**
     * @var int
     */
    private $oid = 0;

    /**
     * Checks if threading is enabled.
     *
     * @return bool True if threading is enabled, otherwise false.
     */
    public static function supported(): bool {
        return \extension_loaded('pthreads');
    }

    /**
     * Spawns a new thread and runs it.
     *
     * @param callable $function The callable to invoke in the thread.
     *
     * @return Thread The thread object that was spawned.
     */
    public static function spawn(callable $function, ...$args) {
        $thread = new self($function, ...$args);
        $thread->start();
        return $thread;
    }

    /**
     * Creates a new thread.
     *
     * @param callable $function The callable to invoke in the thread when run.
     *
     * @throws \Error Thrown if the pthreads extension is not available.
     */
    public function __construct(callable $function, ...$args) {
        if (!self::supported()) {
            throw new \Error("The pthreads extension is required to create threads.");
        }

        $this->function = $function;
        $this->args = $args;
    }

    /**
     * Returns the thread to the condition before starting. The new thread can be started and run independently of the
     * first thread.
     */
    public function __clone() {
        $this->thread = null;
        $this->socket = null;
        $this->pipe = null;
        $this->channel = null;
        $this->oid = 0;
    }

    /**
     * Kills the thread if it is still running.
     *
     * @throws \Amp\Concurrent\ContextException
     */
    public function __destruct() {
        if (\getmypid() === $this->oid) {
            $this->kill();
        }
    }

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning(): bool {
        return null !== $this->pipe && $this->pipe->isReadable();
    }

    /**
     * Spawns the thread and begins the thread's execution.
     *
     * @throws \Amp\Concurrent\StatusError If the thread has already been started.
     * @throws \Amp\Concurrent\ContextException If starting the thread was unsuccessful.
     */
    public function start() {
        if ($this->oid !== 0) {
            throw new StatusError('The thread has already been started.');
        }

        $this->oid = \getmypid();

        list($channel, $this->socket) = \Amp\Socket\pair();

        $this->thread = new Internal\Thread($this->socket, $this->function, $this->args);

        if (!$this->thread->start(PTHREADS_INHERIT_INI)) {
            throw new ContextException('Failed to start the thread.');
        }

        $this->channel = new ChannelledStream($this->pipe = new Socket($channel));
    }

    /**
     * Immediately kills the context.
     *
     * @throws ContextException If killing the thread was unsuccessful.
     */
    public function kill() {
        if ($this->thread !== null) {
            try {
                if ($this->thread->isRunning() && !$this->thread->kill()) {
                    throw new ContextException('Could not kill thread.');
                }
            } finally {
                $this->close();
            }
        }
    }

    /**
     * Closes channel and socket if still open.
     */
    private function close() {
        if ($this->pipe !== null && $this->pipe->isReadable()) {
            $this->pipe->close();
        }

        if (\is_resource($this->socket)) {
            @\fclose($this->socket);
        }

        $this->thread = null;
        $this->channel = null;
    }

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return \Interop\Async\Awaitable<mixed>
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError Thrown if an exit status object is not received.
     */
    public function join(): Awaitable {
        if ($this->channel == null || $this->thread === null) {
            throw new StatusError('The thread has not been started or has already finished.');
        }
        
        return new Coroutine($this->doJoin());
    }
    
    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @throws \Amp\Concurrent\SynchronizationError If the thread does not send an exit status.
     */
    private function doJoin(): \Generator {
        try {
            $response = yield $this->channel->receive();

            if (!$response instanceof ExitStatus) {
                throw new SynchronizationError('Did not receive an exit status from thread.');
            }

            $result = $response->getResult();

            $this->thread->join();
        } catch (\Throwable $exception) {
            $this->kill();
            throw $exception;
        }

        $this->close();

        return $result;
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
            throw new StatusError('The thread has not been started or has already finished.');
        }

        if ($data instanceof ExitStatus) {
            throw new \Error('Cannot send exit status objects.');
        }

        return $this->channel->send($data);
    }
}
