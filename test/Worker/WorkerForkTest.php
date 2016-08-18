<?php

namespace Amp\Tests\Concurrent\Worker;

use Amp\Concurrent\Worker\WorkerFork;

/**
 * @group forking
 * @requires extension pcntl
 */
class WorkerForkTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerFork();
    }
}
