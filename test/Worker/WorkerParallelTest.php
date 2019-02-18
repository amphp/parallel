<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerParallel;

/**
 * @requires extension parallel
 */
class WorkerParallelTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerParallel;
    }
}
