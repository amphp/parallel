<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Loop;
use Icicle\Concurrent\Context;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Concurrent\Task;

class ForkContext implements Context
{
    private $socket;

    public function run(Task $task)
    {
        if (($pid = pcntl_fork()) === -1) {
            throw new \Exception();
        }

        Loop\reInit();

        // We are the parent, so create a server socket.
        if ($pid !== 0) {
            $server = (new ServerFactory())->create('127.0.0.1', 7575);
            $client = (yield $server->accept());
            $data = (yield $client->read(0, "\n"));
            print "Got data from worker: $data\n";
            return;
        }

        // We will have a cloned event loop from the parent after forking. The
        // child context by default is synchronous and uses the parent event
        // loop, so we need to stop the clone before doing any work in case it
        // is already running.
        Loop\clear();
        Loop\stop();

        // To communicate with the parent process, we will connect using a TCP
        // socket. We will use a blocking, synchronous socket that won't interrupt
        // the synchronous work we need to do.
        while (true) {
            $socket = @stream_socket_client('tcp://127.0.0.1:7575', $errno, $errstr, 30);

            // Server hasn't started yet, so keep trying to connect.
            if ($errno === 111) {
                usleep(100);
            } else {
                break;
            }
        }

        try {
            // We are the child, so begin working.
            $task->runHere();

            // Let the parent context now that we are done by sending some data.
            fwrite($socket, 'done');
        } catch (\Throwable $e) {
            fwrite($socket, 'error');
        }

        fwrite($socket, 'done');
        fclose($socket);
        exit(0);
    }
}
