<?php

namespace Amp\Concurrent\Test\Worker;

use Amp\Concurrent\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest {
    protected function createWorker() {
        return new WorkerProcess();
    }
}
