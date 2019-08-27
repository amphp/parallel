<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;

class FunctionsTest extends AsyncTestCase
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

        $task = new Fixtures\TestTask($value);

        $awaitable = Worker\enqueue($task);

        $this->assertSame($value, Promise\wait($awaitable));
    }

    /**
     * @depends testPool
     */
    public function testEnqueueCallable()
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task): Promise {
                return new Success($task->run($this->createMock(Environment::class)));
            }));

        Worker\pool($pool);

        $value = 42;

        $promise = Worker\enqueueCallable('strval', $value);

        $this->assertSame('42', Promise\wait($promise));
    }

    /**
     * @depends testEnqueueCallable
     */
    public function testEnqueueCallableIntegration()
    {
        Worker\pool(new Worker\DefaultPool());

        $value = 42;

        $promise = Worker\enqueueCallable('strval', $value);

        $this->assertSame('42', Promise\wait($promise));
    }

    /**
     * @depends testPool
     */
    public function testWorker()
    {
        $pool = $this->createMock(Pool::class);
        $pool->expects($this->once())
            ->method('getWorker')
            ->will($this->returnValue($this->createMock(Worker\Worker::class)));

        Worker\pool($pool);

        $worker = Worker\worker();
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
        Worker\create(); // shouldn't throw
    }
}
