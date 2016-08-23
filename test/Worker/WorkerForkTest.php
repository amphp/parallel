<?php declare(strict_types = 1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerFork;

/**
 * @group forking
 * @requires extension pcntl
 */
class WorkerForkTest extends AbstractWorkerTest {
    protected function createWorker() {
        return new WorkerFork;
    }
}
