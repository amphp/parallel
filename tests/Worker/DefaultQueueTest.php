<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Awaitable;
use Icicle\Concurrent\Worker\DefaultQueue;
use Icicle\Concurrent\Worker\Task;
use Icicle\Concurrent\Worker\Worker;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

class DefaultQueueTest extends TestCase
{
    /**
     * @param int $min
     * @param int $max
     *
     * @return \Icicle\Concurrent\Worker\Queue
     */
    protected function createQueue($min = null, $max = null)
    {
        $factory = $this->getMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            $running = true;

            $mock = $this->getMock(Worker::class);

            $mock->method('shutdown')
                ->will($this->returnCallback(function () use (&$running) {
                    $running = false;
                    yield 0;
                }));

            $mock->method('kill')
                ->will($this->returnCallback(function () use (&$running) {
                    $running = false;
                }));

            $mock->method('isRunning')
                ->will($this->returnCallback(function () use (&$running) {
                    return $running;
                }));

            $mock->method('enqueue')
                ->will($this->returnCallback(function (Task $task) use ($mock) {
                    yield $mock;
                }));

            return $mock;
        }));

        return new DefaultQueue($min, $max, $factory);
    }

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
            $worker2 = $queue->pull();

            $this->assertNotSame($worker1, $worker2);

            $mock1 = (yield $worker1->enqueue($this->getMock(Task::class)));
            $mock2 = (yield $worker2->enqueue($this->getMock(Task::class)));

            unset($worker1, $worker2);

            $this->assertSame(2, $queue->getWorkerCount());
            $this->assertSame(2, $queue->getIdleWorkerCount());

            $worker3 = $queue->pull();
            $this->assertSame(1, $queue->getIdleWorkerCount());
            $mock3 = (yield $worker3->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock3);

            $worker4 = $queue->pull();
            $this->assertSame(0, $queue->getIdleWorkerCount());
            $this->assertSame(2, $queue->getWorkerCount());
            $mock4 = (yield $worker4->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock2, $mock4);

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
            $this->assertSame(2, $queue->getWorkerCount());
            $this->assertSame(0, $queue->getIdleWorkerCount());

            $mock2 = (yield $worker2->enqueue($this->getMock(Task::class)));

            unset($worker2);

            $this->assertSame(1, $queue->getIdleWorkerCount());

            $worker3 = $queue->pull();
            $this->assertSame(0, $queue->getIdleWorkerCount());
            $this->assertSame(2, $queue->getWorkerCount());

            $mock3 = (yield $worker3->enqueue($this->getMock(Task::class)));

            $this->assertSame($mock2, $mock3);

            yield $queue->shutdown();
        });
    }

    /**
     * @depends testPullPushIsCyclical
     */
    public function testPullReturnsFirstBusyWhenAllBusyAndAtMax()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 2);
            $queue->start();

            $worker1 = $queue->pull();
            $worker2 = $queue->pull();

            $worker3 = $queue->pull();
            $mock1 = (yield $worker1->enqueue($this->getMock(Task::class)));
            $mock3 = (yield $worker3->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock3);

            $worker4 = $queue->pull();
            $mock2 = (yield $worker2->enqueue($this->getMock(Task::class)));
            $mock4 = (yield $worker4->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock2, $mock4);

            $worker5 = $queue->pull();
            $mock5 = (yield $worker5->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock5);

            yield $queue->shutdown();
        });
    }

    /**
     * @depends testPullReturnsFirstBusyWhenAllBusyAndAtMax
     */
    public function testPullSpawnsNewWorkerWhenAllOthersBusyAndBelowMax()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 4);
            $queue->start();

            $worker1 = $queue->pull();
            $worker2 = $queue->pull();
            $this->assertSame(2, $queue->getWorkerCount());

            $worker3 = $queue->pull();
            $this->assertSame(3, $queue->getWorkerCount());
            $mock1 = (yield $worker1->enqueue($this->getMock(Task::class)));
            $mock2 = (yield $worker2->enqueue($this->getMock(Task::class)));
            $mock3 = (yield $worker3->enqueue($this->getMock(Task::class)));
            $this->assertNotSame($mock1, $mock3);
            $this->assertNotSame($mock2, $mock3);

            $worker4 = $queue->pull();
            $this->assertSame(4, $queue->getWorkerCount());
            $mock4 = (yield $worker4->enqueue($this->getMock(Task::class)));
            $this->assertNotSame($mock1, $mock4);
            $this->assertNotSame($mock2, $mock4);
            $this->assertNotSame($mock3, $mock4);

            $worker5 = $queue->pull();
            $this->assertSame(4, $queue->getWorkerCount());
            $mock5 = (yield $worker5->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock5);

            yield $queue->shutdown();
        });
    }

    /**
     * @depends testPullPushIsCyclical
     */
    public function testPushOnlyMarksIdleAfterPushesEqualPulls()
    {
        Coroutine\run(function () {
            $queue = $this->createQueue(2, 2);
            $queue->start();

            $worker1 = $queue->pull();
            $worker2 = $queue->pull();

            $worker3 = $queue->pull();
            $mock1 = (yield $worker1->enqueue($this->getMock(Task::class)));
            $mock2 = (yield $worker2->enqueue($this->getMock(Task::class)));
            $mock3 = (yield $worker3->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock3);

            // Should only mark $worker2 as idle, not $worker3 even though it's pushed first.
            unset($worker3, $worker2);

            // Should pull $worker2 again.
            $worker4 = $queue->pull();
            $mock4 = (yield $worker4->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock2, $mock4);

            // Unsetting $worker1 first, which should now be marked as idle (and so should $worker2/4)
            unset($worker1, $worker4);

            // Should pull $worker1 now since it was marked idle.
            $worker5 = $queue->pull();
            $mock5 = (yield $worker5->enqueue($this->getMock(Task::class)));
            $this->assertSame($mock1, $mock5);

            yield $queue->shutdown();
        });
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
