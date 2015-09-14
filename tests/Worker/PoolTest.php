<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\Pool;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

class PoolTest extends TestCase
{
    public function createPool($min = 8, $max = 32)
    {
        return new Pool($min, $max);
    }

    public function testEnqueue()
    {
        Coroutine\create(function () {
            $pool = $this->createPool();
            $pool->start();

            $returnValue = (yield $pool->enqueue(new TestTask(42)));
            $this->assertEquals(42, $returnValue);

            yield $pool->shutdown();
        })->done();

        Loop\run();
    }
}
