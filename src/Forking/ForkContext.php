<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\PanicError;
use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise\Deferred;

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
        $channels = Channel::create();

        $this->parentSocket = $channels[0];
        $this->childSocket = $channels[1];

        $parentPid = getmypid();
        if (($pid = pcntl_fork()) === -1) {
            throw new \Exception();
        }

        // We are the parent inside this block.
        if ($pid !== 0) {
            $this->pid = $pid;

            // Wait for the child process to send us a byte over the socket pair
            // to discover immediately when the process has completed.
            // @TODO error checking, check message type received
            $receive = new Coroutine($this->parentSocket->receive());
            $receive->then(function ($data) {
                $this->deferred->resolve();
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

    /**
     * {@inheritdoc}
     */
    public function panic($message = '', $code = 0)
    {
        if ($this->isThread) {
            throw new PanicError($message, $code);
        }
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
        /*} catch (\Exception $exception) {
            fwrite($this->childSocket, chr(self::MSG_ERROR));
            $serialized = serialize($exception);
            $length = strlen($serialized);
            fwrite($this->childSocket, pack('S', $length).$serialized);*/
        } finally {
            $this->childSocket->close();
            exit(0);
        }
    }
}
