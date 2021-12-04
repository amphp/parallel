<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cancellation;
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
    public function testPool(): void
    {
        $pool = $this->createMock(Pool::class);

        Worker\pool($pool);

        self::assertSame(Worker\pool(), $pool);
    }

    /**
     * @depends testPool
     */
    public function testEnqueue(): void
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->willReturnCallback(function (Task $task): mixed {
                return $task->run($this->createMock(Environment::class), $this->createMock(Cancellation::class));
            });

        Worker\pool($pool);

        $value = 42;

        $task = new Fixtures\TestTask($value);

        self::assertSame($value, Worker\enqueue($task));
    }

    /**
     * @depends testPool
     */
    public function testEnqueueCallable(): void
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->will(self::returnCallback(function (Task $task): string {
                return $task->run($this->createMock(Environment::class), $this->createMock(Cancellation::class));
            }));

        Worker\pool($pool);

        $value = 42;

        self::assertSame('42', Worker\enqueueCallable('strval', $value));
    }

    /**
     * @depends testEnqueueCallable
     */
    public function testEnqueueCallableIntegration(): void
    {
        Worker\pool($pool = new Worker\DefaultPool);

        $value = 42;

        self::assertSame('42', Worker\enqueueCallable('strval', $value));

        $pool->shutdown();
    }

    /**
     * @depends testEnqueueCallable
     */
    public function testEnqueueNonAutoloadableCallable(): void
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
    public function testWorker(): void
    {
        $pool = $this->createMock(Pool::class);
        $pool->expects(self::once())
            ->method('getWorker')
            ->will(self::returnValue($this->createMock(Worker\Worker::class)));

        Worker\pool($pool);

        $worker = Worker\worker();

        self::assertInstanceOf(Worker\Worker::class, $worker);
    }

    public function testFactory(): void
    {
        $factory = $this->createMock(WorkerFactory::class);

        Worker\factory($factory);

        self::assertSame(Worker\factory(), $factory);
    }

    /**
     * @depends testFactory
     */
    public function testCreate(): void
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->will(self::returnValue($this->createMock(Worker\Worker::class)));

        Worker\factory($factory);
        Worker\create(); // shouldn't throw
    }
}
