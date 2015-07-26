<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\ContextAbortException;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise\Deferred;
use Icicle\Socket\Stream\DuplexStream;

/**
 * Implements a UNIX-compatible context using forked processes.
 */
class ForkContext extends Synchronized implements ContextInterface
{
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

    private $parentSocket;
    private $childSocket;
    private $pid = 0;
    private $isChild = false;
    private $deferred;
    private $function;

    /**
     * Creates a new fork context.
     *
     * @param callable $function The function to run in the context.
     */
    public function __construct(callable $function)
    {
        parent::__construct();

        $this->function = $function;

        $this->deferred = new Deferred(function (\Exception $exception) {
            $this->stop();
        });
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
        // If we are the child process, then we must be running, don't you think?
        if ($this->isChild) {
            return true;
        }

        return posix_getpgid($this->pid) !== false;
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
        Loop\stop();
        Loop\reInit();
        Loop\clear();

        // Execute the context runnable and send the parent context the result.
        $this->run();
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

    public function __destruct()
    {
        parent::__destruct();

        // The parent process outlives the child process, so don't destroy the
        // semaphore until the parent exits.
        if (!$this->isChild) {
            //$this->semaphore->destroy();
        }
    }

    private function run()
    {
        try {
            $generator = call_user_func($this->function);
            if ($generator instanceof \Generator) {
                $coroutine = new Coroutine($generator);
            }
            Loop\run();
            fwrite($this->childSocket, chr(self::MSG_DONE));
        } catch (\Exception $exception) {
            fwrite($this->childSocket, chr(self::MSG_ERROR));
            $serialized = serialize($exception);
            $length = strlen($serialized);
            fwrite($this->childSocket, pack('S', $length) . $serialized);
        } finally {
            fclose($this->childSocket);
            exit(0);
        }
    }
}
