<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\WorkerProcess;

/**
 * @group process
 */
class ProcessPoolTest extends AbstractPoolTest {
    protected function createPool($min = null, $max = null) {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerProcess;
        }));

        return new DefaultPool($min, $max, $factory);
    }
}
