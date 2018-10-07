<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerProcess;
    }
}
