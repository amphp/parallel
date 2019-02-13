<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerParallel;

class WorkerParallelTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerParallel;
    }
}
