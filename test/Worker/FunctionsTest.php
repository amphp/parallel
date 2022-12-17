<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\WorkerPool;
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
        $pool = $this->createMock(WorkerPool::class);

        Worker\workerPool($pool);

        self::assertSame(Worker\workerPool(), $pool);
    }

    /**
     * @depends testPool
     */
    public function testSubmit(): void
    {
        $pool = $this->createMock(WorkerPool::class);
        $pool->method('submit')
            ->willReturnCallback(function (Task $task): Worker\Execution {
                $channel = $this->createMock(Channel::class);

                $future = Future::complete($task->run(
                    $channel,
                    $this->createMock(Cache::class),
                    $this->createMock(Cancellation::class),
                ));

                return new Worker\Execution($task, $channel, $future);
            });

        Worker\workerPool($pool);

        $value = 42;

        $task = new Fixtures\TestTask($value);

        self::assertSame($value, Worker\submit($task)->getResult()->await());
    }

    /**
     * @depends testPool
     */
    public function testWorker(): void
    {
        $pool = $this->createMock(WorkerPool::class);
        $pool->expects(self::once())
            ->method('getWorker')
            ->will(self::returnValue($this->createMock(Worker\Worker::class)));

        Worker\workerPool($pool);

        $worker = Worker\getWorker();

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
