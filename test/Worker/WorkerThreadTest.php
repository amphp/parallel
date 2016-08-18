<?php

namespace Amp\Concurrent\Test\Worker;

use Amp\Concurrent\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest {
    protected function createWorker() {
        return new WorkerThread();
    }
}
