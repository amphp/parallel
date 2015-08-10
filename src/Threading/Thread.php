<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ExecutorInterface;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitFailure;
use Icicle\Concurrent\Sync\ExitSuccess;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

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
        $executor = new ThreadExecutor($this, new Channel($this->socket));

        $coroutine = new Coroutine($this->execute($executor));
        $coroutine->done();

        Loop\run();
    }

    /**
     * @param \Icicle\Concurrent\ExecutorInterface
     *
     * @return \Generator
     */
    private function execute(ExecutorInterface $executor)
    {
        try {
            $function = $this->function;
            if ($function instanceof \Closure) {
                $function = $function->bindTo($executor, ThreadExecutor::class);
            }

            $result = new ExitSuccess(yield call_user_func_array($function, $this->args));
        } catch (\Exception $exception) {
            $result = new ExitFailure($exception);
        }

        yield $executor->send($result);

        yield $executor->close();
    }
}
