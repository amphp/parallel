<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;

abstract class AbstractPoolTest extends AsyncTestCase
{
    /**
     * @param int $max
     *
     * @return Pool
     */
    abstract protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool;

    public function testIsRunning(): \Generator
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->isRunning());

        yield $pool->shutdown();
        $this->assertFalse($pool->isRunning());
    }

    public function testIsIdleOnStart(): \Generator
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->isIdle());

        yield $pool->shutdown();
    }

    public function testShutdownShouldReturnSameResult(): \Generator
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->isIdle());

        $result = yield $pool->shutdown();
        $this->assertSame($result, yield $pool->shutdown());
    }

    public function testPullShouldThrowStatusError(): \Generator
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('The pool was shutdown');

        $pool = $this->createPool();

        $this->assertTrue($pool->isIdle());

        yield $pool->shutdown();

        yield $pool->getWorker();
    }

    public function testGetMaxSize(): void
    {
        $pool = $this->createPool(17);
        $this->assertEquals(17, $pool->getMaxSize());
    }

    public function testWorkersIdleOnStart(): \Generator
    {
        $pool = $this->createPool();

        $this->assertEquals(0, $pool->getIdleWorkerCount());

        yield $pool->shutdown();
    }

    public function testEnqueue(): \Generator
    {
        $pool = $this->createPool();

        $returnValue = yield $pool->enqueue(new Fixtures\TestTask(42));
        $this->assertEquals(42, $returnValue);

        yield $pool->shutdown();
    }

    public function testEnqueueMultiple(): \Generator
    {
        $pool = $this->createPool();

        $values = yield \Amp\Promise\all([
                $pool->enqueue(new Fixtures\TestTask(42)),
                $pool->enqueue(new Fixtures\TestTask(56)),
                $pool->enqueue(new Fixtures\TestTask(72))
            ]);

        $this->assertEquals([42, 56, 72], $values);

        yield $pool->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(1000);

        $pool = $this->createPool();

        $pool->kill();

        $this->assertFalse($pool->isRunning());
    }

    public function testGet(): \Generator
    {
        $pool = $this->createPool();

        $worker = yield $pool->getWorker();
        $this->assertInstanceOf(Worker::class, $worker);

        $this->assertTrue($worker->isRunning());
        $this->assertTrue($worker->isIdle());

        $this->assertSame(42, yield $worker->enqueue(new Fixtures\TestTask(42)));

        yield $worker->shutdown();

        $worker->kill();
    }

    public function testBusyPool(): \Generator
    {
        $pool = $this->createPool(2);

        $values = [42, 56, 72];
        $tasks = \array_map(function (int $value): Task {
            return new Fixtures\TestTask($value);
        }, $values);

        $promises = \array_map(function (Task $task) use ($pool): Promise {
            return $pool->enqueue($task);
        }, $tasks);

        $this->assertSame($values, yield $promises);

        $promises = \array_map(function (Task $task) use ($pool): Promise {
            return $pool->enqueue($task);
        }, $tasks);

        $this->assertSame($values, yield $promises);

        yield $pool->shutdown();
    }

    public function testCreatePoolShouldThrowError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Maximum size must be a non-negative integer');

        $this->createPool(-1);
    }

    public function testCleanGarbageCollection(): \Generator
    {
        // See https://github.com/amphp/parallel-functions/issues/5
        for ($i = 0; $i < 3; $i++) {
            $pool = $this->createPool(32);

            $values = \range(1, 50);
            $tasks = \array_map(function (int $value): Task {
                return new Fixtures\TestTask($value);
            }, $values);

            $promises = \array_map(function (Task $task) use ($pool): Promise {
                return $pool->enqueue($task);
            }, $tasks);

            $this->assertSame($values, yield $promises);
        }
    }

    public function testPooledKill(): \Generator
    {
        // See https://github.com/amphp/parallel/issues/66
        $pool = $this->createPool(1);
        $worker = yield $pool->getWorker();
        $worker->kill();

        $this->expectWarning();
        $this->expectWarningMessage('Worker in pool crashed');

        $worker->__destruct();

        $worker = yield $pool->getWorker();
    }
}
