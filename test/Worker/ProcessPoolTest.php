<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\WorkerProcess;

/**
 * @group process
 */
class ProcessPoolTest extends AbstractPoolTest
{
    protected function createPool(int $max = Pool::DEFAULT_MAX_SIZE): Pool
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            return new WorkerProcess;
        });

        return new DefaultPool($max, $factory);
    }
}
