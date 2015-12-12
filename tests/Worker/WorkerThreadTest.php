<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest
{
    protected function createWorker()
    {
        return new WorkerThread();
    }
}
