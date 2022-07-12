<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\LocalCache;
use Amp\Future;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\DefaultWorkerFactory;
use Amp\Parallel\Worker\DefaultWorkerPool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerPool;
use Revolt\EventLoop;

abstract class AbstractPoolTest extends AbstractWorkerTest
{
    public function testNotIdleOnSubmit(): void
    {
        // Skip, because workers ARE idle even after submitting a job
        $this->expectNotToPerformAssertions();
    }

    public function testShutdownShouldReturnSameResult(): void
    {
        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());

        $result = $pool->shutdown();
        self::assertSame($result, $pool->shutdown());
    }

    public function testPullShouldThrowStatusError(): void
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('shut down');

        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());

        $pool->shutdown();

        $pool->getWorker();
    }

    public function testGetMaxSize(): void
    {
        $pool = $this->createPool(17);
        self::assertEquals(17, $pool->getLimit());
    }

    public function testWorkersIdleOnStart(): void
    {
        $pool = $this->createPool();

        self::assertEquals(0, $pool->getIdleWorkerCount());

        $pool->shutdown();
    }

    public function testGet(): void
    {
        $pool = $this->createPool();

        $worker = $pool->getWorker();
        self::assertInstanceOf(Worker::class, $worker);

        self::assertTrue($worker->isRunning());
        self::assertTrue($worker->isIdle());

        self::assertSame(42, $worker->submit(new Fixtures\TestTask(42))->getResult()->await());

        $worker->shutdown();

        $worker->kill();
    }

    public function testBusyPool(): void
    {
        $pool = $this->createPool(2);

        $values = [42, 56, 72];
        $tasks = \array_map(function (int $value): Task {
            return new Fixtures\TestTask($value);
        }, $values);

        $promises = \array_map(function (Task $task) use ($pool): Future {
            return $pool->submit($task)->getResult();
        }, $tasks);

        self::assertEquals($values, Future\await($promises));

        $promises = \array_map(function (Task $task) use ($pool): Future {
            return $pool->submit($task)->getResult();
        }, $tasks);

        self::assertEquals($values, Future\await($promises));

        $pool->shutdown();
    }

    public function testCreatePoolShouldThrowError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Maximum size must be a non-negative integer');

        $this->createPool(-1);
    }

    public function testCleanGarbageCollection(): void
    {
        // See https://github.com/amphp/parallel-functions/issues/5
        for ($i = 0; $i < 3; $i++) {
            $pool = $this->createPool(32);

            $values = \range(1, 50);

            $promises = \array_map(static function (int $value) use ($pool): Future {
                return $pool->submit(new Fixtures\TestTask($value))->getResult();
            }, $values);

            self::assertEquals($values, Future\await($promises));
        }
    }

    public function testPooledKill(): void
    {
        EventLoop::setErrorHandler(function (\Throwable $exception): void {
            $this->assertStringContainsString("Worker in pool crashed", $exception->getMessage());
        });

        $this->setTimeout(1);

        // See https://github.com/amphp/parallel/issues/66
        $pool = $this->createPool(1);
        $worker1 = $pool->getWorker();
        $worker1->kill();

        unset($worker1);

        $worker2 = $pool->getWorker();
    }

    protected function createWorker(string $cacheClass = LocalCache::class, ?string $autoloadPath = null): Worker
    {
        return $this->createPool(cacheClass: $cacheClass, autoloadPath: $autoloadPath);
    }

    protected function createPool(
        int $max = WorkerPool::DEFAULT_WORKER_LIMIT,
        string $cacheClass = LocalCache::class,
        ?string $autoloadPath = null
    ): WorkerPool {
        $factory = new DefaultWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: $this->createContextFactory(),
            cacheClass: $cacheClass,
        );

        return new DefaultWorkerPool($max, $factory);
    }
}
