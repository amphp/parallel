<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\Channel;

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
            ->willReturnCallback(function (Task $task): Worker\Job {
                $channel = $this->createMock(Channel::class);

                $future = Future::complete($task->run(
                    $channel,
                    $this->createMock(Cache::class),
                    $this->createMock(Cancellation::class),
                ));

                return new Worker\Job($task, $channel, $future);
            });

        Worker\pool($pool);

        $value = 42;

        $task = new Fixtures\TestTask($value);

        self::assertSame($value, Worker\enqueue($task)->getFuture()->await());
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

        $worker = Worker\pooledWorker();

        self::assertInstanceOf(Worker\Worker::class, $worker);
    }

    public function testFactory(): void
    {
        $factory = $this->createMock(WorkerFactory::class);

        Worker\workerFactory($factory);

        self::assertSame(Worker\workerFactory(), $factory);
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

        Worker\workerFactory($factory);
        Worker\createWorker(); // shouldn't throw
    }
}
