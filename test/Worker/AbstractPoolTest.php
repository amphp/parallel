<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Revolt\EventLoop\Loop;
use function Amp\async;
use function Amp\await;

abstract class AbstractPoolTest extends AsyncTestCase
{
    public function testIsRunning()
    {
        $pool = $this->createPool();

        self::assertTrue($pool->isRunning());

        $pool->shutdown();
        self::assertFalse($pool->isRunning());
    }

    public function testIsIdleOnStart()
    {
        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());

        $pool->shutdown();
    }

    public function testShutdownShouldReturnSameResult()
    {
        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());

        $result = $pool->shutdown();
        self::assertSame($result, $pool->shutdown());
    }

    public function testPullShouldThrowStatusError()
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('The pool was shutdown');

        $pool = $this->createPool();

        self::assertTrue($pool->isIdle());

        $pool->shutdown();

        $pool->getWorker();
    }

    public function testGetMaxSize(): void
    {
        $pool = $this->createPool(17);
        self::assertEquals(17, $pool->getMaxSize());
    }

    public function testWorkersIdleOnStart()
    {
        $pool = $this->createPool();

        self::assertEquals(0, $pool->getIdleWorkerCount());

        $pool->shutdown();
    }

    public function testEnqueue()
    {
        $pool = $this->createPool();

        $returnValue = $pool->enqueue(new Fixtures\TestTask(42));
        self::assertEquals(42, $returnValue);

        $pool->shutdown();
    }

    public function testEnqueueMultiple()
    {
        $pool = $this->createPool();

        $values = await([
            async(fn () => $pool->enqueue(new Fixtures\TestTask(42))),
            async(fn () => $pool->enqueue(new Fixtures\TestTask(56))),
            async(fn () => $pool->enqueue(new Fixtures\TestTask(72))),
        ]);

        self::assertEquals([42, 56, 72], $values);

        $pool->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(1000);

        $pool = $this->createPool();

        $pool->kill();

        self::assertFalse($pool->isRunning());
    }

    public function testGet()
    {
        $pool = $this->createPool();

        $worker = $pool->getWorker();
        self::assertInstanceOf(Worker::class, $worker);

        self::assertTrue($worker->isRunning());
        self::assertTrue($worker->isIdle());

        self::assertSame(42, $worker->enqueue(new Fixtures\TestTask(42)));

        $worker->shutdown();

        $worker->kill();
    }

    public function testBusyPool()
    {
        $pool = $this->createPool(2);

        $values = [42, 56, 72];
        $tasks = \array_map(function (int $value): Task {
            return new Fixtures\TestTask($value);
        }, $values);

        $promises = \array_map(function (Task $task) use ($pool): Promise {
            return async(fn () => $pool->enqueue($task));
        }, $tasks);

        self::assertSame($values, await($promises));

        $promises = \array_map(function (Task $task) use ($pool): Promise {
            return async(fn () => $pool->enqueue($task));
        }, $tasks);

        self::assertSame($values, await($promises));

        $pool->shutdown();
    }

    public function testCreatePoolShouldThrowError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Maximum size must be a non-negative integer');

        $this->createPool(-1);
    }

    public function testCleanGarbageCollection()
    {
        // See https://github.com/amphp/parallel-functions/issues/5
        for ($i = 0; $i < 3; $i++) {
            $pool = $this->createPool(32);

            $values = \range(1, 50);
            $tasks = \array_map(function (int $value): Task {
                return new Fixtures\TestTask($value);
            }, $values);

            $promises = \array_map(function (Task $task) use ($pool): Promise {
                return async(fn () => $pool->enqueue($task));
            }, $tasks);

            self::assertSame($values, await($promises));
        }
    }

    public function testPooledKill()
    {
        Loop::setErrorHandler(function (\Throwable $exception): void {
            $this->assertStringContainsString("Worker in pool crashed", $exception->getMessage());
        });

        $this->setTimeout(100);

        // See https://github.com/amphp/parallel/issues/66
        $pool = $this->createPool(1);
        $worker1 = $pool->getWorker();
        $worker1->kill();

        unset($worker1);

        $worker2 = $pool->getWorker();
    }

    /**
     * @param int $max
     *
     * @return Pool
     */
    abstract protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool;
}
