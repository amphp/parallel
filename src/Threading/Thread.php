<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

/**
 * An internal thread that executes a given function concurrently.
 *
 * @internal
 */
class Thread extends \Thread
{
    /**
     * @var string Path to an autoloader to include.
     */
    public $autoloaderPath;

    /**
     * @var callable The function to execute in the thread.
     */
    private $function;

    /**
     * @var
     */
    private $args;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var bool
     */
    private $lock = true;

    /**
     * Creates a new thread object.
     *
     * @param resource $socket IPC communication socket.
     * @param callable $function The function to execute in the thread.
     * @param mixed[] $args Arguments to pass to the function.
     * @param string $autoloaderPath Path to autoloader include file.
     */
    public function __construct($socket, callable $function, array $args = [], $autoloaderPath = '')
    {
        $this->autoloaderPath = $autoloaderPath;
        $this->function = $function;
        $this->args = $args;
        $this->socket = $socket;
    }

    /**
     * Runs the thread code and the initialized function.
     */
    public function run()
    {
        /* First thing we need to do is initialize the class autoloader. If we
         * don't do this first, objects we receive from other threads will just
         * be garbage data and unserializable values (like resources) will be
         * lost. This happens even with thread-safe objects.
         */
        if ('' !== $this->autoloaderPath) {
            require $this->autoloaderPath;
        }

        // At this point, the thread environment has been prepared so begin using the thread.
        $channel = new Channel($this->socket);

        $coroutine = new Coroutine($this->execute($channel));
        $coroutine->done();

        Loop\run();
    }

    /**
     * Attempts to obtain the lock. Returns true if the lock was obtained.
     *
     * @return bool
     */
    public function tsl()
    {
        if (!$this->lock) {
            return false;
        }

        $this->lock();

        try {
            if ($this->lock) {
                $this->lock = false;
                return true;
            }
            return false;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Releases the lock.
     */
    public function release()
    {
        $this->lock = true;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Concurrent\Sync\ChannelInterface $channel
     *
     * @return \Generator
     *
     * @resolve int
     */
    private function execute(ChannelInterface $channel)
    {
        $executor = new ThreadExecutor($this, $channel);

        try {
            $function = $this->function;
            if ($function instanceof \Closure) {
                $function = $function->bindTo($executor, ThreadExecutor::class);
            }

            $result = new ExitSuccess(yield call_user_func_array($function, $this->args));
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        try {
            yield $channel->send($result);
        } finally {
            $channel->close();
        }
    }
}
