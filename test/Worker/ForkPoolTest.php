<?php declare(strict_types = 1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\{ DefaultPool, WorkerFactory, WorkerFork };

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkPoolTest extends AbstractPoolTest {
    protected function createPool($min = null, $max = null) {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerFork;
        }));

        return new DefaultPool($min, $max, $factory);
    }
}
