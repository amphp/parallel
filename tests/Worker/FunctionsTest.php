<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Queue;
use Icicle\Concurrent\Worker\Pool;
use Icicle\Concurrent\Worker\Task;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\Coroutine\Coroutine;
use Icicle\Tests\Concurrent\TestCase;

class FunctionsTest extends TestCase
{
    public function testGetPool()
    {
        $pool = Worker\pool();

        $this->assertInstanceOf(Pool::class, $pool);
    }

    /**
     * @depends testGetPool
     */
    public function testSetPool()
    {
        $pool = $this->getMock(Pool::class);

        Worker\pool($pool);

        $this->assertSame($pool, Worker\pool());
    }

    /**
     * @depends testSetPool
     */
    public function testEnqueue()
    {
        $pool = $this->getMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task) {
                yield $task->run($this->getMock(Environment::class));
            }));

        Worker\pool($pool);

        $value = 42;

        $task = new TestTask($value);

        $coroutine = new Coroutine(Worker\enqueue($task));

        $this->assertSame($value, $coroutine->wait());
    }

    public function testGetQueue()
    {
        $queue = Worker\queue();

        $this->assertInstanceOf(Queue::class, $queue);
    }

    /**
     * @depends testGetQueue
     */
    public function testSetQueue()
    {
        $queue = $this->getMock(Queue::class);

        Worker\queue($queue);

        $this->assertSame($queue, Worker\queue());
    }

    /**
     * @depends testSetQueue
     */
    public function testPull()
    {
        $queue = $this->getMock(Queue::class);
        $queue->expects($this->once())
            ->method('pull')
            ->will($this->returnValue($this->getMock(Worker\Worker::class)));

        Worker\queue($queue);

        $worker = Worker\pull();
    }

    public function testGetFactory()
    {
        $factory = Worker\factory();

        $this->assertInstanceOf(WorkerFactory::class, $factory);
    }

    /**
     * @depends testGetFactory
     */
    public function testSetFactory()
    {
        $factory = $this->getMock(WorkerFactory::class);

        Worker\factory($factory);

        $this->assertSame($factory, Worker\factory());
    }

    /**
     * @depends testSetFactory
     */
    public function testCreate()
    {
        $factory = $this->getMock(WorkerFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->getMock(Worker\Worker::class)));

        Worker\factory($factory);

        $worker = Worker\create();
    }
}
