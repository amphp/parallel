<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\DefaultQueue;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\Concurrent\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadQueueTest extends AbstractQueueTest
{
    protected function createQueue($min = 0, $max = 0)
    {
        $factory = $this->getMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerThread();
        }));

        return new DefaultQueue($min, $max, $factory);
    }
}
