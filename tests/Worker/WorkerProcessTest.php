<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerProcess();
    }
}
