<?php

namespace Amp\Tests\Concurrent\Worker;

use Amp\Concurrent\Worker;
use Amp\Concurrent\Worker\{Environment, Pool, Task, WorkerFactory};
use Amp\Coroutine;
use Amp\Tests\Concurrent\TestCase;

class FunctionsTest extends TestCase
{
    public function testPool()
    {
        $pool = $this->getMock(Pool::class);

        Worker\pool($pool);

        $this->assertTrue($pool === Worker\pool());
    }

    /**
     * @depends testPool
     */
    public function testEnqueue()
    {
        $pool = $this->getMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task) {
                return yield $task->run($this->getMock(Environment::class));
            }));

        Worker\pool($pool);

        $value = 42;

        $task = new TestTask($value);

        $coroutine = new Coroutine(Worker\enqueue($task));

        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testPool
     */
    public function testGet()
    {
        $pool = $this->getMock(Pool::class);
        $pool->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this->getMock(Worker\Worker::class)));

        Worker\pool($pool);

        $worker = Worker\get();
    }

    public function testFactory()
    {
        $factory = $this->getMock(WorkerFactory::class);

        Worker\factory($factory);

        $this->assertTrue($factory === Worker\factory());
    }

    /**
     * @depends testFactory
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
