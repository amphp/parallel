<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Concurrent\Worker\WorkerThreadFactory;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadPoolTest extends AbstractPoolTest
{
    protected function createPool($min = null, $max = null)
    {
        return new DefaultPool($min, $max, new WorkerThreadFactory());
    }
}
