<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\BasicEnvironment;
use Amp\Parallel\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest
{
    protected function createWorker(string $envClassName = BasicEnvironment::class, string $autoloadPath = null)
    {
        return new WorkerThread($envClassName, $autoloadPath);
    }
}
