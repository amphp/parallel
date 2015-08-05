<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\PanicError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Promise;
use Icicle\Socket\Stream\DuplexStream;

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
     * @var \Thread A thread instance.
     */
    public $thread;

    /**
     * @var DuplexStream An active socket connection to the thread's socket.
     */
    private $socket;

    /**
     * @var A reference handle to the invoker.
     */
    private $invoker;

    /**
     * @var Channel A channel for communicating with the thread.
     */
    private $channel;

    private $isThread = false;

    /**
     * Creates an instance of the current context class for the local thread.
     *
     * @internal
     *
     * @return self
     */
    final public static function createLocalInstance(Thread $thread)
    {
        $class = new \ReflectionClass(static::class);
        $instance = $class->newInstanceWithoutConstructor();
        $instance->thread = $thread;
        $instance->isThread = true;
        return $instance;
    }

    /**
     * Creates a new thread context.
     *
     * @param callable $function The function to run in the thread.
     */
    public function __construct(callable $function)
    {
        $this->deferredJoin = new Promise\Deferred(function () {
            $this->kill();
        });

        $this->thread = new Thread($function);
        $this->thread->autoloaderPath = $this->getComposerAutoloader();
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
        $channels = Channel::create();
        $this->channel = new Channel($channels[1]);

        // Start the thread first. The thread will prepare the autoloader and
        // the event loop, and then notify us when the thread environment is
        // ready. If we don't do this first, objects will break when passed
        // to the thread, since the classes are not yet defined.
        $this->thread->start(PTHREADS_INHERIT_INI | PTHREADS_ALLOW_GLOBALS);

        // The thread must prepare itself first, so wait until the thread has
        // done so. We need to unlock ourselves while waiting to prevent
        // deadlocks if we somehow acquired the lock before the thread did.
        $this->thread->synchronized(function () {
            if (!$this->thread->prepared) {
                $this->thread->wait();
            }
        });

        // At this stage, the thread environment has been prepared, and we kept
        // the lock from above, so initialize the thread with the necessary
        // values to be copied over.
        $this->thread->synchronized(function () use ($channels) {
            $this->thread->init($channels[0]);
            $this->thread->notify();
        });

        /*$this->socket->read(1)->then(function ($data) {
            $message = ord($data);
            if ($message === Thread::MSG_DONE) {
                $this->deferredJoin->resolve();
                $this->thread->join();
                return;
            }

            // Get the fatal exception from the process.
            return $this->socket->read(2)->then(function ($data) {
                $serializedLength = unpack('S', $data);
                $serializedLength = $serializedLength[1];
                return $this->socket->read($serializedLength);
            })->then(function ($data) {
                $panic = unserialize($data);
                $exception = new PanicError($panic['message'], $panic['code'], $panic['trace']);
                $this->deferredJoin->reject($exception);
                $this->socket->close();
            });
        }, function (\Exception $exception) {
            $this->deferredJoin->reject($exception);
        });*/
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->thread->kill();
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
    public function panic($message = '', $code = 0)
    {
        if ($this->isThread) {
            throw new PanicError($message, $code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        yield $this->channel->receive();
        $this->thread->join();
    }

    /**
     * {@inheritdoc}
     */
    public function lock()
    {
        $this->thread->lock();
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        $this->thread->unlock();
    }

    /**
     * {@inheritdoc}
     */
    public function synchronized(callable $callback)
    {
        $this->lock();

        try {
            $returnValue = $callback($this);
        } finally {
            $this->unlock();
        }

        return $returnValue;
    }

    /**
     * Gets the full path to the Composer autoloader.
     *
     * If no Composer autoloader is being used, `null` is returned.
     *
     * @return \Composer\Autoload\ClassLoader|null
     */
    private function getComposerAutoloader()
    {
        foreach (get_included_files() as $path) {
            if (strpos($path, 'vendor/autoload.php') !== false) {
                $source = file_get_contents($path);
                if (strpos($source, '@generated by Composer') !== false) {
                    return $path;
                }
            }
        }

        // Find the Composer autoloader initializer class, and use it to fetch
        // the autoloader instance.
        /*foreach (get_declared_classes() as $name) {
        if (strpos($name, 'ComposerAutoloaderInit') === 0) {
        return $name::getLoader();
        }
        }*/

        return;
    }
}
