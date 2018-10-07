<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Success;

class FunctionsTest extends TestCase
{
    public function testPool()
    {
        $pool = $this->createMock(Pool::class);

        Worker\pool($pool);

        $this->assertSame(Worker\pool(), $pool);
    }

    /**
     * @depends testPool
     */
    public function testEnqueue()
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task): Promise {
                return new Success($task->run($this->createMock(Environment::class)));
            }));

        Worker\pool($pool);

        $value = 42;

        $task = new TestTask($value);

        $awaitable = Worker\enqueue($task);

        $this->assertSame($value, Promise\wait($awaitable));
    }

    /**
     * @depends testPool
     */
    public function testGet()
    {
        $pool = $this->createMock(Pool::class);
        $pool->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this->createMock(Worker\Worker::class)));

        Worker\pool($pool);

        $worker = Worker\get();
    }

    public function testFactory()
    {
        $factory = $this->createMock(WorkerFactory::class);

        Worker\factory($factory);

        $this->assertSame(Worker\factory(), $factory);
    }

    /**
     * @depends testFactory
     */
    public function testCreate()
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->createMock(Worker\Worker::class)));

        Worker\factory($factory);

        $worker = Worker\create();
    }
}
