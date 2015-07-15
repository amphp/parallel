<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ContextAbortException;
use Icicle\Promise;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements an execution context using native multi-threading.
 *
 * The thread context is not itself threaded. A local instance of the context is
 * maintained both in the context that creates the thread and in the thread
 * itself.
 */
abstract class ThreadContext implements ContextInterface
{
    /**
     * @var \Thread A thread instance.
     */
    public $thread;

    /**
     * @var Promise\Deferred A deferred object that resolves when the context ends.
     */
    private $deferredJoin;

    /**
     * @var DuplexStream An active socket connection to the thread's socket.
     */
    private $socket;

    public static function createThreadInstance()
    {
        $class = new \ReflectionClass(static::class);
        return $class->newInstanceWithoutConstructor();
    }

    /**
     * Creates a new thread context.
     */
    public function __construct()
    {
        $this->deferredJoin = new Promise\Deferred(function () {
            $this->kill();
        });

        $this->thread = new Thread(static::class);
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
        if (($sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new \Exception();
        }

        // When the thread is started, the event loop will be duplicated, so we
        // need to start the thread before we add anything else to the event loop
        // or we will cause a segmentation fault.
        $this->thread->initialize($sockets[1]);
        $this->thread->start(PTHREADS_INHERIT_ALL);

        $this->socket = new DuplexStream($sockets[0]);

        $this->socket->read(1)->then(function ($data) {
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
                $previous = unserialize($data);
                $exception = new ContextAbortException('The context encountered an error.', 0, $previous);
                $this->deferredJoin->reject($exception);
                $this->socket->close();
            });
        }, function (\Exception $exception) {
            $this->deferredJoin->reject($exception);
        });
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
    public function join()
    {
        return $this->deferredJoin->getPromise();
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
}
