<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Future;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Test\Worker\Fixtures\TestTask;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerPool;

abstract class AbstractPoolTest extends AbstractWorkerTest
{
    public function testNotIdleOnSubmit(): void
    {
        // Skip, because workers ARE idle even after submitting a job
        $this->expectNotToPerformAssertions();
    }

    public function testMultipleShutdownCalls(): void
    {
        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());
        self::assertTrue($pool->isRunning());

        $pool->shutdown();

        self::assertFalse($pool->isRunning());

        $pool->shutdown();
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

        self::assertSame(42, $worker->submit(new Fixtures\TestTask(42))->await());

        $worker->shutdown();

        $worker->kill();
    }

    public function testBusyPool(): void
    {
        $pool = $this->createPool(1);

        $values = [42, 56, 72];
        $tasks = \array_map(function (int $value): Task {
            return new Fixtures\TestTask($value);
        }, $values);

        $futures = \array_map(function (Task $task) use ($pool): Future {
            return $pool->submit($task)->getFuture();
        }, $tasks);

        self::assertEquals($values, Future\await($futures));

        $futures = \array_map(function (Task $task) use ($pool): Future {
            return $pool->submit($task)->getFuture();
        }, $tasks);

        self::assertEquals($values, Future\await($futures));

        $pool->shutdown();
    }

    public function testCreatePoolShouldThrowError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Maximum size must be a positive integer');

        $this->createPool(-1);
    }

    public function testCleanGarbageCollection(): void
    {
        // See https://github.com/amphp/parallel-functions/issues/5
        for ($i = 0; $i < 3; $i++) {
            $pool = $this->createPool(32);

            $values = \range(1, 50);

            $futures = \array_map(static function (int $value) use ($pool): Future {
                return $pool->submit(new Fixtures\TestTask($value))->getFuture();
            }, $values);

            self::assertEquals($values, Future\await($futures));
        }
    }

    /**
     * @see https://github.com/amphp/parallel/issues/66
     */
    public function testPooledKill(): void
    {
        $this->setTimeout(10);
        \set_error_handler(static function (int $errno, string $errstr) use (&$error): void {
            $error = $errstr;
        });

        try {
            $pool = $this->createPool(1);
            $worker1 = $pool->getWorker();
            $worker1->kill();
            self::assertFalse($worker1->isRunning());

            unset($worker1); // Destroying the worker will trigger the pool to recognize it has been killed.

            $worker2 = $pool->getWorker();
            self::assertTrue($worker2->isRunning());

            self::assertStringContainsString('Worker in pool crashed', $error);
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @see https://github.com/amphp/parallel/issues/177
     */
    public function testWaitingForAvailableWorker(): void
    {
        $count = 4;
        $delay = 0.1;

        $this->setMinimumRuntime($delay * $count);
        $this->setTimeout($delay * $count + $delay);

        $pool = $this->createPool(1);

        $executions = [];
        for ($i = 0; $i < $count; $i++) {
            $executions[] = $pool->submit(new TestTask($i, $delay));
        }

        Future\await(\array_map(fn (Execution $e) => $e->getFuture(), $executions));
    }

    protected function createWorker(?string $autoloadPath = null): Worker
    {
        return $this->createPool(autoloadPath: $autoloadPath);
    }

    protected function createPool(
        int $max = WorkerPool::DEFAULT_WORKER_LIMIT,
        ?string $autoloadPath = null
    ): WorkerPool {
        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: $this->createContextFactory(),
        );

        return new ContextWorkerPool($max, $factory);
    }
}
