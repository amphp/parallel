<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\WorkerException;
use Amp\PHPUnit\TestCase;

class NonAutoloadableTask implements Task
{
    public function run(Environment $environment)
    {
        return 1;
    }
}

abstract class AbstractWorkerTest extends TestCase
{
    /**
     * @return \Amp\Parallel\Worker\Worker
     */
    abstract protected function createWorker();

    public function testWorkerConstantDefined()
    {
        Loop::run(function () {
            $worker = $this->createWorker();
            $this->assertTrue(yield $worker->enqueue(new ConstantTask));
            yield $worker->shutdown();
        });
    }

    public function testIsRunning()
    {
        Loop::run(function () {
            $worker = $this->createWorker();
            $this->assertFalse($worker->isRunning());

            $worker->enqueue(new TestTask(42)); // Enqueue a task to start the worker.

            $this->assertTrue($worker->isRunning());

            yield $worker->shutdown();
            $this->assertFalse($worker->isRunning());
        });
    }

    public function testIsIdleOnStart()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $this->assertTrue($worker->isIdle());

            yield $worker->shutdown();
        });
    }

    /**
     * @expectedException         \Amp\Parallel\Context\StatusError
     * @expectedExceptionMessage  The worker has been shut down
     */
    public function testEnqueueShouldThrowStatusError()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $this->assertTrue($worker->isIdle());

            yield $worker->shutdown();
            yield $worker->enqueue(new TestTask(42));
        });
    }

    public function testEnqueue()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $returnValue = yield $worker->enqueue(new TestTask(42));
            $this->assertEquals(42, $returnValue);

            yield $worker->shutdown();
        });
    }

    public function testEnqueueMultipleSynchronous()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $values = yield \Amp\Promise\all([
                $worker->enqueue(new TestTask(42)),
                $worker->enqueue(new TestTask(56)),
                $worker->enqueue(new TestTask(72))
            ]);

            $this->assertEquals([42, 56, 72], $values);

            yield $worker->shutdown();
        });
    }

    public function testEnqueueMultipleAsynchronous()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $promises = [
                $worker->enqueue(new TestTask(42, 200)),
                $worker->enqueue(new TestTask(56, 300)),
                $worker->enqueue(new TestTask(72, 100))
            ];

            $expected = [42, 56, 72];
            foreach ($promises as $promise) {
                $promise->onResolve(function ($e, $v) use (&$expected) {
                    $this->assertSame(\array_shift($expected), $v);
                });
            }

            yield $promises; // Wait until all tasks have finished before invoking $worker->shutdown().

            yield $worker->shutdown();
        });
    }

    public function testEnqueueMultipleThenShutdown()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $promises = [
                $worker->enqueue(new TestTask(42, 200)),
                $worker->enqueue(new TestTask(56, 300)),
                $worker->enqueue(new TestTask(72, 100))
            ];

            yield $worker->shutdown();

            \array_shift($promises); // First task will succeed.

            foreach ($promises as $promise) {
                $promise->onResolve(function ($e, $v) {
                    $this->assertInstanceOf(WorkerException::class, $e);
                });
            }
        });
    }

    public function testNotIdleOnEnqueue()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $coroutine = $worker->enqueue(new TestTask(42));
            $this->assertFalse($worker->isIdle());
            yield $coroutine;

            yield $worker->shutdown();
        });
    }

    public function testKill()
    {
        $worker = $this->createWorker();

        $worker->enqueue(new TestTask(42));

        $this->assertRunTimeLessThan([$worker, 'kill'], 250);
        $this->assertFalse($worker->isRunning());
    }

    public function testNonAutoloadableTask()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            try {
                yield $worker->enqueue(new NonAutoloadableTask);
                $this->fail("Tasks that cannot be autoloaded should throw an exception");
            } catch (TaskError $exception) {
                $this->assertSame("Error", $exception->getName());
                $this->assertGreaterThan(0, \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class)));
            }

            yield $worker->shutdown();
        });
    }

    public function testUnserializableTask()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            try {
                yield $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                    public function run(Environment $environment)
                    {
                    }
                });
                $this->fail("Tasks that cannot be autoloaded should throw an exception");
            } catch (SerializationException $exception) {
                $this->assertSame(0, \strpos($exception->getMessage(), "The given data cannot be sent because it is not serializable"));
            }

            yield $worker->shutdown();
        });
    }

    public function testUnserializableTaskFollowedByValidTask()
    {
        Loop::run(function () {
            $worker = $this->createWorker();

            $promise1 = $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                public function run(Environment $environment)
                {
                }
            });
            $promise2 = $worker->enqueue(new TestTask(42));

            $this->assertSame(42, yield $promise2);

            yield $worker->shutdown();
        });
    }
}
