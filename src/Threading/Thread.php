<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Promise;

/**
 * An internal thread that executes a given function concurrently.
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

    private $prepared = false;
    private $initialized = false;

    /**
     * @var resource
     */
    private $socket;

    /**
     * Creates a new thread object.
     *
     * @param callable $function The function to execute in the thread.
     * @param mixed[]|null $args Arguments to pass to the function.
     * @param string $autoloaderPath Path to autoloader include file.
     */
    public function __construct(callable $function, array $args = [], $autoloaderPath = '')
    {
        $this->autoloaderPath = $autoloaderPath;
        $this->function = $function;
        $this->args = $args;
    }

    /**
     * Initializes the thread by injecting values from the parent into threaded memory.
     *
     * @param resource $socket The channel socket to communicate to the parent with.
     */
    public function init($socket)
    {
        $this->socket = $socket;
        $this->initialized = true;
    }

    /**
     * Determines if the thread has successfully been prepared.
     *
     * @return bool
     */
    public function isPrepared()
    {
        return $this->prepared;
    }

    /**
     * Runs the thread code and the initialized function.
     */
    public function run()
    {
        // First thing we need to do is prepare the thread environment to make
        // it usable, so lock the thread while we do it. Hopefully we get the
        // lock first, but if we don't the parent will release and give us a
        // chance before continuing.
        $this->lock();

        // First thing we need to do is initialize the class autoloader. If we
        // don't do this first, objects we receive from other threads will just
        // be garbage data and unserializable values (like resources) will be
        // lost. This happens even with thread-safe objects.
        if ('' !== $this->autoloaderPath) {
            require $this->autoloaderPath;
        }

        // Now let the parent thread know that we are done preparing the
        // thread environment and are ready to accept data.
        $this->prepared = true;
        $this->notify();
        $this->unlock();

        // Wait for objects to be injected by the context wrapper object.
        $this->lock();
        if (!$this->initialized) {
            $this->wait();
        }
        $this->unlock();

        // At this point, the thread environment has been prepared, and the
        // parent has finished injecting values into our memory, so begin using
        // the channel.
        $channel = new Channel($this->socket);

        Promise\wait(new Coroutine($this->execute($channel)));
    }

    /**
     * @param Channel $channel
     *
     * @return \Generator
     */
    private function execute(Channel $channel)
    {
        try {
            $result = new ExitSuccess(
                yield call_user_func_array($this->function, array_merge([$channel], $this->args))
            );
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        yield $channel->send($result);

        $channel->close();
    }
}
