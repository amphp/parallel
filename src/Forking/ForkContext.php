<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ContextAbortException;
use Icicle\Loop;
use Icicle\Promise\Deferred;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
abstract class ForkContext extends Synchronized implements ContextInterface
{
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

    private $parentSocket;
    private $childSocket;
    private $pid = 0;
    private $isChild = false;
    private $deferred;
    public $sem;

    /**
     * Creates a new fork context.
     */
    public function __construct()
    {
        parent::__construct();

        $this->deferred = new Deferred(function (\Exception $exception) {
            $this->stop();
        });

        $this->sem = new AsyncIpcSemaphore();
    }

    /**
     * Gets the forked process's process ID.
     *
     * @return int The process ID.
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        if (!$this->isChild) {
            return posix_getpgid($this->pid) !== false;
        }

        return true;
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

        $parentPid = getmypid();
        if (($pid = pcntl_fork()) === -1) {
            throw new \Exception();
        }

        if ($pid !== 0) {
            // We are the parent, so close the child socket.
            $this->pid = $pid;
            fclose($this->childSocket);

            Loop\signal(SIGUSR1, function () {
                $this->sem->update();
            });

            // Wait for the child process to send us a byte over the socket pair
            // to discover immediately when the process has completed.
            $this->parentSocket->read(1)->then(function ($data) {
                $message = ord($data);
                if ($message === self::MSG_DONE) {
                    $this->deferred->resolve();
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
                    $this->deferred->reject($exception);
                    $this->parentSocket->close();
                });
            }, function (\Exception $exception) {
                $this->deferred->reject($exception);
            });

            return;
        }

        // We are the child, so close the parent socket and initialize child values.
        $this->isChild = true;
        $this->pid = getmypid();
        $this->parentSocket->close();

        // We will have a cloned event loop from the parent after forking. The
        // child context by default is synchronous and uses the parent event
        // loop, so we need to stop the clone before doing any work in case it
        // is already running.
        Loop\reInit();
        Loop\clear();
        Loop\stop();

        pcntl_signal(SIGUSR1, function () {
            $this->sem->update();
        });

        // Execute the context runnable and send the parent context the result.
        try {
            $this->run();
            pcntl_signal_dispatch();
            fwrite($this->childSocket, chr(self::MSG_DONE));
        } catch (\Exception $exception) {
            fwrite($this->childSocket, chr(self::MSG_ERROR));
            $serialized = serialize($exception);
            $length = strlen($serialized);
            fwrite($this->childSocket, pack('S', $length).$serialized);
        } finally {
            fclose($this->childSocket);
            exit(0);
        }
    }

    public function stop()
    {
        if ($this->isRunning()) {
            // send the SIGTERM signal to ask the process to end
            posix_kill($this->getPid(), SIGTERM);
        }
    }

    public function kill()
    {
        if ($this->isRunning()) {
            // forcefully kill the process using SIGKILL
            posix_kill($this->getPid(), SIGKILL);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function join()
    {
        if ($this->isChild) {
            throw new \Exception();
        }

        return $this->deferred->getPromise();
    }

    /**
     * {@inheritdoc}
     */
    abstract public function run();

    public function __destruct()
    {
        parent::__destruct();

        // The parent process outlives the child process, so don't destroy the
        // semaphore until the parent exits.
        if (!$this->isChild) {
            $this->semaphore->destroy();
            $this->sem->destroy();
        }
    }
}
