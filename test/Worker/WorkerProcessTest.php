<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\BasicEnvironment;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function createWorker(string $envClassName = BasicEnvironment::class, string $autoloadPath = null): Worker
    {
        return new WorkerProcess($envClassName, [], null, $autoloadPath);
    }
}
