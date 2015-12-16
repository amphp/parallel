<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Awaitable;
use Icicle\Concurrent\Worker\Worker;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

abstract class AbstractQueueTest extends TestCase
{
    /**
     * @param int $min
     * @param int $max
     *
     * @return \Icicle\Concurrent\Worker\Queue
     */
    abstract protected function createQueue($min = 0, $max = 0);

    public function testIsRunning()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue();
            $this->assertFalse($queue->isRunning());

            $queue->start();
            $this->assertTrue($queue->isRunning());

            yield $queue->shutdown();
            $this->assertFalse($queue->isRunning());
        });
    }

    public function testGetMinSize()
    {
        $queue = $this->createQueue(7, 24);
        $this->assertEquals(7, $queue->getMinSize());
    }

    public function testGetMaxSize()
    {
        $queue = $this->createQueue(3, 17);
        $this->assertEquals(17, $queue->getMaxSize());
    }

    public function testMinWorkersSpawnedOnStart()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(8, 32);
            $queue->start();

            $this->assertEquals(8, $queue->getWorkerCount());

            yield $queue->shutdown();
        });
    }

    public function testWorkersIdleOnStart()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(8, 32);
            $queue->start();

            $this->assertEquals(8, $queue->getIdleWorkerCount());

            yield $queue->shutdown();
        });
    }

    public function testPullPushIsCyclical()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 2);
            $queue->start();

            $worker1 = $queue->pull();
            $queue->push($worker1);

            $worker2 = $queue->pull();
            $queue->push($worker2);

            $this->assertNotSame($worker1, $worker2);

            $worker3 = $queue->pull();
            $this->assertSame($worker1, $worker3);

            $worker4 = $queue->pull();
            $this->assertSame($worker2, $worker4);

            yield $queue->shutdown();
        });
    }

    /**
     * @depends testPullPushIsCyclical
     */
    public function testPullReturnsLastPushedWhenBusy()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 2);
            $queue->start();

            $worker1 = $queue->pull();
            $worker2 = $queue->pull();

            $queue->push($worker2);

            $worker3 = $queue->pull();
            $this->assertSame($worker2, $worker3);

            yield $queue->shutdown();
        });
    }

    /**
     * @depends testPullPushIsCyclical
     */
    public function testPullReturnsFirstBusyWhenAllBusy()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 2);
            $queue->start();

            $worker1 = $queue->pull();
            $worker2 = $queue->pull();

            $worker3 = $queue->pull();

            $this->assertSame($worker1, $worker3);

            $worker4 = $queue->pull();
            $this->assertSame($worker2, $worker4);

            $worker5 = $queue->pull();
            $this->assertSame($worker1, $worker5);

            yield $queue->shutdown();
        });
    }

    /**
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testPushForeignWorker()
    {
        $queue = $this->createQueue();
        $queue->start();
        $queue->push($this->getMock(Worker::class));
    }

    public function testKill()
    {
        $queue = $this->createQueue();
        $queue->start();

        $worker = $queue->pull();

        $this->assertRunTimeLessThan([$queue, 'kill'], 0.5);
        $this->assertFalse($queue->isRunning());
        $this->assertFalse($worker->isRunning());
    }
}
