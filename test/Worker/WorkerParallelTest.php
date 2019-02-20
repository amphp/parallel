<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\BasicEnvironment;
use Amp\Parallel\Worker\WorkerParallel;

/**
 * @requires extension parallel
 */
class WorkerParallelTest extends AbstractWorkerTest
{
    protected function createWorker(string $envClassName = BasicEnvironment::class, string $autoloadPath = null)
    {
        return new WorkerParallel($envClassName, $autoloadPath);
    }
}
