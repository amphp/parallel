<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\WorkerThread;

/**
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerThread();
    }
}
