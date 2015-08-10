<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ExitInterface;
use Icicle\Promise;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
class ThreadContext implements ContextInterface
{
    /**
     * @var \Icicle\Concurrent\Threading\Thread A thread instance.
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\Sync\Channel A channel for communicating with the thread.
     */
    private $channel;

    /**
     * Creates a new thread context from a thread.
     *
     * @param callable $function
     */
    public function __construct(callable $function /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        list($channel, $socket) = Channel::createSocketPair();

        $this->channel = new Channel($channel);
        $this->thread = new Thread($socket, $function, $args, $this->getComposerAutoloader());
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->thread->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->thread->start(PTHREADS_INHERIT_INI);
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->thread->kill();
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        try {
            $response = (yield $this->channel->receive());

            if (!$response instanceof ExitInterface) {
                throw new SynchronizationError('Did not receive an exit status from thread.');
            }

            yield $response->getResult();
        } finally {
            $this->channel->close();
            $this->thread->join();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        $data = (yield $this->channel->receive());

        if ($data instanceof ExitInterface) {
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
        return $this->channel->send($data);
    }

    /**
     * Gets the full path to the Composer autoloader.
     *
     * If no Composer autoloader is being used, `null` is returned.
     *
     * @return string
     */
    private function getComposerAutoloader()
    {
        static $path;

        if (null !== $path) {
            return $path;
        }

        foreach (get_included_files() as $path) {
            if (preg_match('/vendor\/autoload.php$/i', $path)) {
                return $path;
            }
        }

        return $path = '';
    }
}
