<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\LocalCache;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerParallel;

/**
 * @requires extension parallel
 */
class WorkerParallelTest extends AbstractWorkerTest
{
    protected function createWorker(string $cacheClass = LocalCache::class, string $autoloadPath = null): Worker
    {
        return new WorkerParallel($cacheClass, $autoloadPath);
    }
}
