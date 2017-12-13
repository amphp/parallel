<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadPoolTest extends AbstractPoolTest {
    protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerThread;
        }));

        return new DefaultPool($max, $factory);
    }
}
