<?php
namespace Icicle\Concurrent\Worker;

class WorkerFactory
{
    public function create()
    {
        if (extension_loaded('pthreads')) {
            return new WorkerThread();
        }

        if (extension_loaded('pcntl')) {
            return new WorkerFork();
        }

        return new WorkerProcess();
    }
}
