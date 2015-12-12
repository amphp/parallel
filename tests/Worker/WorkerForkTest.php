<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\WorkerFork;

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
