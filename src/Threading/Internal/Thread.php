<?php
namespace Icicle\Concurrent\Threading\Internal;

use Icicle\Concurrent\Sync\{Channel, ChannelledStream, Internal\ExitFailure, Internal\ExitSuccess};
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Stream\Pipe\DuplexPipe;

/**
 * An internal thread that executes a given function concurrently.
 *
 * @internal
 */
class Thread extends \Thread
{
    const KILL_CHECK_FREQUENCY = 0.25;

    /**
     * @var callable The function to execute in the thread.
     */
    private $function;

    /**
     * @var mixed[] Arguments to pass to the function.
     */
    private $args;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var bool
     */
    private $killed = false;

    /**
     * Creates a new thread object.
     *
     * @param resource $socket   IPC communication socket.
     * @param callable $function The function to execute in the thread.
     * @param mixed[]  $args     Arguments to pass to the function.
     */
    public function __construct($socket, callable $function, array $args = [])
    {
        $this->function = $function;
        $this->args = $args;
        $this->socket = $socket;
    }

    /**
     * Runs the thread code and the initialized function.
     *
     * @codeCoverageIgnore Only executed in thread.
     */
    public function run()
    {
        /* First thing we need to do is re-initialize the class autoloader. If
         * we don't do this first, any object of a class that was loaded after
         * the thread started will just be garbage data and unserializable
         * values (like resources) will be lost. This happens even with
         * thread-safe objects.
         */
        foreach (get_declared_classes() as $className) {
            if (strpos($className, 'ComposerAutoloaderInit') === 0) {
                // Calling getLoader() will register the class loader for us
                $className::getLoader();
                break;
            }
        }

        Loop\loop($loop = Loop\create(false)); // Disable signals in thread.

        // At this point, the thread environment has been prepared so begin using the thread.
        $channel = new ChannelledStream(new DuplexPipe($this->socket, false));

        $coroutine = new Coroutine($this->execute($channel));
        $coroutine->done();

        $timer = $loop->timer(self::KILL_CHECK_FREQUENCY, true, function () use ($loop) {
            if ($this->killed) {
                $loop->stop();
            }
        });
        $timer->unreference();

        $loop->run();
    }

    /**
     * Sets a local variable to true so the running event loop can check for a kill signal.
     */
    public function kill()
    {
        return $this->killed = true;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Concurrent\Sync\Channel $channel
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @codeCoverageIgnore Only executed in thread.
     */
    private function execute(Channel $channel): \Generator
    {
        try {
            if ($this->function instanceof \Closure) {
                $function = $this->function->bindTo($channel, Channel::class);
            }

            if (empty($function)) {
                $function = $this->function;
            }

            $result = new ExitSuccess(yield $function(...$this->args));
        } catch (\Throwable $exception) {
            $result = new ExitFailure($exception);
        }

        // Attempt to return the result.
        try {
            return yield from $channel->send($result);
        } catch (\Throwable $exception) {
            // The result was not sendable! Try sending the reason why instead.
            return yield from $channel->send(new ExitFailure($exception));
        }
    }
}
