<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\LocalCache;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function createWorker(string $cacheClass = LocalCache::class, string $autoloadPath = null): Worker
    {
        return WorkerProcess::start($cacheClass, bootstrapPath: $autoloadPath);
    }
}
