<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest {
    protected function createWorker() {
        return new WorkerThread;
    }
}
