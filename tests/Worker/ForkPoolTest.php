<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Concurrent\Worker\WorkerForkFactory;

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkPoolTest extends AbstractPoolTest
{
    protected function createPool($min = null, $max = null)
    {
        return new DefaultPool($min, $max, new WorkerForkFactory());
    }
}
