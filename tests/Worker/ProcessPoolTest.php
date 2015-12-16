<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\Concurrent\Worker\WorkerProcess;

/**
 * @group process
 */
class ProcessPoolTest extends AbstractPoolTest
{
    protected function createPool($min = null, $max = null)
    {
        $factory = $this->getMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerProcess();
        }));

        return new DefaultPool($min, $max, $factory);
    }
}
