<?php

namespace Amp\Parallel\Test\Worker;

use Amp\CancellationToken;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ContextPanicError;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\BasicEnvironment;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskCancelledException;
use Amp\Parallel\Worker\TaskFailureError;
use Amp\Parallel\Worker\TaskFailureException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellationToken;

class NonAutoloadableTask implements Task
{
    public function run(Environment $environment, CancellationToken $token)
    {
        return 1;
    }
}

abstract class AbstractWorkerTest extends AsyncTestCase
{
    /**
     * @param string $envClassName
     * @param string|null $autoloadPath
     *
     * @return \Amp\Parallel\Worker\Worker
     */
    abstract protected function createWorker(string $envClassName = BasicEnvironment::class, string $autoloadPath = null);

    public function testWorkerConstantDefined(): \Generator
    {
        $worker = $this->createWorker();
        $this->assertTrue(yield $worker->enqueue(new Fixtures\ConstantTask));
        yield $worker->shutdown();
    }

    public function testIsRunning(): \Generator
    {
        $worker = $this->createWorker();
        $this->assertTrue($worker->isRunning());

        $worker->enqueue(new Fixtures\TestTask(42)); // Enqueue a task to start the worker.

        $this->assertTrue($worker->isRunning());

        yield $worker->shutdown();
        $this->assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart(): \Generator
    {
        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        yield $worker->shutdown();
    }

    public function testEnqueueShouldThrowStatusError(): \Generator
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('The worker has been shut down');

        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        yield $worker->shutdown();
        yield $worker->enqueue(new Fixtures\TestTask(42));
    }

    public function testEnqueue(): \Generator
    {
        $worker = $this->createWorker();

        $returnValue = yield $worker->enqueue(new Fixtures\TestTask(42));
        $this->assertEquals(42, $returnValue);

        yield $worker->shutdown();
    }

    public function testEnqueueMultipleSynchronous(): \Generator
    {
        $worker = $this->createWorker();

        $values = yield \Amp\Promise\all([
                $worker->enqueue(new Fixtures\TestTask(42)),
                $worker->enqueue(new Fixtures\TestTask(56)),
                $worker->enqueue(new Fixtures\TestTask(72))
            ]);

        $this->assertEquals([42, 56, 72], $values);

        yield $worker->shutdown();
    }

    public function testEnqueueMultipleAsynchronous(): \Generator
    {
        $worker = $this->createWorker();

        $promises = [
            $worker->enqueue(new Fixtures\TestTask(42, 200)),
            $worker->enqueue(new Fixtures\TestTask(56, 300)),
            $worker->enqueue(new Fixtures\TestTask(72, 100))
        ];

        $expected = [72, 42, 56];
        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) use (&$expected) {
                $this->assertSame(\array_shift($expected), $v);
            });
        }

        yield $promises; // Wait until all tasks have finished before invoking $worker->shutdown().

        yield $worker->shutdown();
    }

    public function testEnqueueMultipleThenShutdown(): \Generator
    {
        $worker = $this->createWorker();

        $promises = [
            $worker->enqueue(new Fixtures\TestTask(42, 200)),
            $worker->enqueue(new Fixtures\TestTask(56, 300)),
            $worker->enqueue(new Fixtures\TestTask(72, 100))
        ];

        $promise = $worker->shutdown(); // Send shutdown signal, but don't await until tasks have finished.

        $expected = [72, 42, 56];
        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) use (&$expected) {
                $this->assertSame(\array_shift($expected), $v);
            });
        }

        yield $promise; // Await shutdown before ending test.
    }

    public function testNotIdleOnEnqueue(): \Generator
    {
        $worker = $this->createWorker();

        $coroutine = $worker->enqueue(new Fixtures\TestTask(42));
        $this->assertFalse($worker->isIdle());
        yield $coroutine;

        yield $worker->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(500);


        $worker = $this->createWorker();

        $worker->enqueue(new Fixtures\TestTask(42));

        $worker->kill();

        $this->assertFalse($worker->isRunning());
    }

    public function testFailingTaskWithException(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Exception::class));
        } catch (TaskFailureException $exception) {
            $this->assertSame(\Exception::class, $exception->getOriginalClassName());
        }

        yield $worker->shutdown();
    }

    public function testFailingTaskWithError(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Error::class));
        } catch (TaskFailureError $exception) {
            $this->assertSame(\Error::class, $exception->getOriginalClassName());
        }

        yield $worker->shutdown();
    }

    public function testFailingTaskWithPreviousException(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Error::class, \Exception::class));
        } catch (TaskFailureError $exception) {
            $this->assertSame(\Error::class, $exception->getOriginalClassName());
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(TaskFailureException::class, $previous);
            $this->assertSame(\Exception::class, $previous->getOriginalClassName());
        }

        yield $worker->shutdown();
    }

    public function testNonAutoloadableTask(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new NonAutoloadableTask);
            $this->fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (TaskFailureError $exception) {
            $this->assertSame("Error", $exception->getOriginalClassName());
            $this->assertGreaterThan(0, \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class)));
        }

        yield $worker->shutdown();
    }

    public function testUnserializableTask(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                public function run(Environment $environment, CancellationToken $token)
                {
                }
            });
            $this->fail("Tasks that cannot be serialized should throw an exception");
        } catch (SerializationException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "The given data could not be serialized"));
        }

        yield $worker->shutdown();
    }

    public function testUnserializableResult(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\UnserializableResultTask);
            $this->fail("Tasks results that cannot be serialized should throw an exception");
        } catch (TaskFailureException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "Uncaught Amp\Serialization\SerializationException in worker"));
        }

        yield $worker->shutdown();
    }

    public function testNonAutoloadableResult(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\NonAutoloadableResultTask);
            $this->fail("Tasks results that cannot be autoloaded should throw an exception");
        } catch (\Error $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "Class instances returned from Amp\Parallel\Worker\Task::run() must be autoloadable by the Composer autoloader"));
        }

        yield $worker->shutdown();
    }

    public function testUnserializableTaskFollowedByValidTask(): \Generator
    {
        $worker = $this->createWorker();

        $promise1 = $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
            public function run(Environment $environment, CancellationToken $token)
            {
            }
        });
        $promise2 = $worker->enqueue(new Fixtures\TestTask(42));

        $this->assertSame(42, yield $promise2);

        yield $worker->shutdown();
    }

    public function testCustomAutoloader(): \Generator
    {
        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/custom-bootstrap.php');

        $this->assertTrue(yield $worker->enqueue(new Fixtures\AutoloadTestTask));

        yield $worker->shutdown();
    }

    public function testInvalidCustomAutoloader(): \Generator
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('No file found at bootstrap file path given');

        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/not-found.php');

        yield $worker->enqueue(new Fixtures\AutoloadTestTask);

        yield $worker->shutdown();
    }

    public function testCancellableTask(): \Generator
    {
        $this->expectException(TaskCancelledException::class);

        $worker = $this->createWorker();

        yield $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellationToken(100));

        yield $worker->shutdown();
    }

    public function testEnqueueAfterCancelledTask(): \Generator
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellationToken(100));
            $this->fail(TaskCancelledException::class . ' did not fail enqueue promise');
        } catch (TaskCancelledException $exception) {
            // Task should be cancelled, ignore this exception.
        }

        $this->assertTrue(yield $worker->enqueue(new Fixtures\ConstantTask));

        yield $worker->shutdown();
    }

    public function testCancellingCompletedTask(): \Generator
    {
        $worker = $this->createWorker();

        $this->assertTrue(yield $worker->enqueue(new Fixtures\ConstantTask(), new TimeoutCancellationToken(100)));

        yield $worker->shutdown();
    }
}
