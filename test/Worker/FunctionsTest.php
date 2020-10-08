<?php

namespace Amp\Parallel\Test\Worker;

use Amp\CancellationToken;
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;

function nonAutoloadableFunction(): void
{
    // Empty test function
}

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
            ->will($this->returnCallback(function (Task $task): mixed {
                return $task->run($this->createMock(Environment::class), $this->createMock(CancellationToken::class));
            }));

        Worker\pool($pool);

        $value = 42;

        $task = new Fixtures\TestTask($value);

        $this->assertSame($value, Worker\enqueue($task));
    }

    /**
     * @depends testPool
     */
    public function testEnqueueCallable()
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task): string {
                return $task->run($this->createMock(Environment::class), $this->createMock(CancellationToken::class));
            }));

        Worker\pool($pool);

        $value = 42;

        $this->assertSame('42', Worker\enqueueCallable('strval', $value));
    }

    /**
     * @depends testEnqueueCallable
     */
    public function testEnqueueCallableIntegration()
    {
        Worker\pool($pool = new Worker\DefaultPool);

        $value = 42;

        $this->assertSame('42', Worker\enqueueCallable('strval', $value));

        $pool->shutdown();
    }

    /**
     * @depends testEnqueueCallable
     */
    public function testEnqueueNonAutoloadableCallable()
    {
        $this->expectException(Worker\TaskError::class);
        $this->expectExceptionMessage('User-defined functions must be autoloadable');

        Worker\pool($pool = new Worker\DefaultPool);

        try {
            Worker\enqueueCallable(__NAMESPACE__ . '\\nonAutoloadableFunction');
        } finally {
            $pool->shutdown();
        }
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

        $this->assertInstanceOf(Worker\Worker::class, $worker);
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
