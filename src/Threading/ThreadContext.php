<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ContextAbortException;
use Icicle\Promise;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements an execution context using native multi-threading.
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

    private $parentSocket;
    private $clientSocket;

    /**
     * Creates a new thread context.
     */
    public function __construct()
    {
        $this->deferredJoin = new Promise\Deferred(function () {
            $this->kill();
        });

        $this->thread = new Thread();
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
        if (($fd = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new \Exception();
        }

        $this->parentSocket = new DuplexStream($fd[0]);
        $this->childSocket = $fd[1];

        $this->parentSocket->read(1)->then(function ($data) {
            $message = ord($data);
            if ($message === Thread::MSG_DONE) {
                $this->thread->join();
                $this->deferredJoin->resolve();
                return;
            }

            // Get the fatal exception from the process.
            return $this->parentSocket->read(2)->then(function ($data) {
                $serializedLength = unpack('S', $data);
                $serializedLength = $serializedLength[1];
                return $this->parentSocket->read($serializedLength);
            })->then(function ($data) {
                $previous = unserialize($data);
                $exception = new ContextAbortException('The context encountered an error.', 0, $previous);
                $this->deferredJoin->reject($exception);
                $this->parentSocket->close();
            });
        }, function (\Exception $exception) {
            $this->deferredJoin->reject($exception);
        });

        $this->thread->initialize($this->childSocket);
        $this->thread->start(PTHREADS_INHERIT_ALL);
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
    public function synchronized(\Closure $callback)
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
     * Initializes the thread and executes the main context code.
     */
    private function initializeThread()
    {
        $this->run();
    }
}
