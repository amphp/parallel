<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Loop;
use Icicle\Concurrent\Context;
use Icicle\Socket\Stream\DuplexStream;
use Icicle\Promise\Deferred;

abstract class ForkContext implements Context
{
    private $parentSocket;
    private $childSocket;
    private $pid = 0;
    private $isChild = false;

    public function getPid()
    {
        return $this->pid;
    }

    public function isRunning()
    {
        if (!$this->isChild) {
            return posix_getpgid($this->pid) !== false;
        }

        return true;
    }

    public function join()
    {
        pcntl_waitpid($this->pid, $status);
    }

    public function start()
    {
        $deferred = new Deferred(function (\Exception $exception) {
            $this->stop();
        });

        if (($fd = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new \Exception();
        }

        $this->parentSocket = new DuplexStream($fd[0]);
        $this->childSocket = $fd[1];

        if (($pid = pcntl_fork()) === -1) {
            throw new \Exception();
        }

        Loop\reInit();

        // We are the parent, so create a server socket.
        if ($pid !== 0) {
            $this->pid = $pid;
            $this->parentSocket->read(0, "\n")->then(function ($data) use ($deferred) {
                print "Got data from worker: $data\n";
                $deferred->resolve();
            }, function (\Exception $exception) use ($deferred) {
                $deferred->reject($exception);
            });

            return $deferred->getPromise();
        }

        // We will have a cloned event loop from the parent after forking. The
        // child context by default is synchronous and uses the parent event
        // loop, so we need to stop the clone before doing any work in case it
        // is already running.
        Loop\clear();
        Loop\stop();

        $this->pid = getmypid();

        try {
            // We are the child, so begin working.
            $this->run();

            // Let the parent context now that we are done by sending some data.
            fwrite($this->childSocket, 'done');
        } catch (\Throwable $e) {
            fwrite($this->childSocket, 'error');
        }

        fwrite($this->childSocket, 'done');
        fclose($this->childSocket);
        exit(0);
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

    public function lock()
    {
    }

    public function unlock()
    {
    }

    public function synchronize(callable $callback)
    {
    }

    abstract public function run();
}
