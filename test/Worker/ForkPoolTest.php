<?php

namespace Amp\Concurrent\Test\Worker;

use Amp\Concurrent\Worker\{ DefaultPool, WorkerFactory, WorkerFork };

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkPoolTest extends AbstractPoolTest {
    protected function createPool($min = null, $max = null) {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerFork();
        }));

        return new DefaultPool($min, $max, $factory);
    }
}
