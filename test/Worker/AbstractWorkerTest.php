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
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellationToken;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

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
     * @return Worker
     */
    abstract protected function createWorker(string $envClassName = BasicEnvironment::class, string $autoloadPath = null): Worker;

    public function testWorkerConstantDefined()
    {
        $worker = $this->createWorker();
        $this->assertTrue($worker->enqueue(new Fixtures\ConstantTask));
        $worker->shutdown();
    }

    public function testIsRunning()
    {
        $worker = $this->createWorker();
        $this->assertTrue($worker->isRunning());

        $worker->enqueue(new Fixtures\TestTask(42)); // Enqueue a task to start the worker.

        $this->assertTrue($worker->isRunning());

        $worker->shutdown();
        $this->assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart()
    {
        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        $worker->shutdown();
    }

    public function testEnqueueShouldThrowStatusError()
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('The worker has been shut down');

        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        $worker->shutdown();
        $worker->enqueue(new Fixtures\TestTask(42));
    }

    public function testEnqueue()
    {
        $worker = $this->createWorker();

        $returnValue = $worker->enqueue(new Fixtures\TestTask(42));
        $this->assertEquals(42, $returnValue);

        $worker->shutdown();
    }

    public function testEnqueueMultipleSynchronous()
    {
        $worker = $this->createWorker();

        $values = await([
                async(fn() => $worker->enqueue(new Fixtures\TestTask(42))),
                async(fn() => $worker->enqueue(new Fixtures\TestTask(56))),
                async(fn() => $worker->enqueue(new Fixtures\TestTask(72))),
            ]);

        $this->assertEquals([42, 56, 72], $values);

        $worker->shutdown();
    }

    public function testEnqueueMultipleAsynchronous()
    {
        $worker = $this->createWorker();

        $promises = [
            async(fn() => $worker->enqueue(new Fixtures\TestTask(42, 200))),
            async(fn() => $worker->enqueue(new Fixtures\TestTask(56, 300))),
            async(fn() => $worker->enqueue(new Fixtures\TestTask(72, 100))),
        ];

        $expected = [72, 42, 56];
        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) use (&$expected) {
                $this->assertSame(\array_shift($expected), $v);
            });
        }

        await($promises); // Wait until all tasks have finished before invoking $worker->shutdown().

        $worker->shutdown();
    }

    public function testEnqueueMultipleThenShutdown()
    {
        $worker = $this->createWorker();

        $promises = [
            async(fn() => $worker->enqueue(new Fixtures\TestTask(42, 200))),
            async(fn() => $worker->enqueue(new Fixtures\TestTask(56, 300))),
            async(fn() => $worker->enqueue(new Fixtures\TestTask(72, 100))),
        ];

        $shutdown = async(fn() => $worker->shutdown()); // Send shutdown signal, but don't await until tasks have finished.

        $expected = [72, 42, 56];
        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) use (&$expected) {
                $this->assertSame(\array_shift($expected), $v);
            });
        }

        await($promise);

        await($shutdown); // Await shutdown before ending test.
    }

    public function testNotIdleOnEnqueue()
    {
        $worker = $this->createWorker();

        $promise = async(fn() => $worker->enqueue(new Fixtures\TestTask(42)));
        delay(0); // Tick event loop to call Worker::enqueue()
        $this->assertFalse($worker->isIdle());
        await($promise);

        $worker->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(500);

        $worker = $this->createWorker();

        async(fn() => $worker->enqueue(new Fixtures\TestTask(42)));

        $worker->kill();

        $this->assertFalse($worker->isRunning());
    }

    public function testFailingTaskWithException()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Exception::class));
        } catch (TaskFailureException $exception) {
            $this->assertSame(\Exception::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithError()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Error::class));
        } catch (TaskFailureError $exception) {
            $this->assertSame(\Error::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithPreviousException()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Error::class, \Exception::class));
        } catch (TaskFailureError $exception) {
            $this->assertSame(\Error::class, $exception->getOriginalClassName());
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(TaskFailureException::class, $previous);
            $this->assertSame(\Exception::class, $previous->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new NonAutoloadableTask);
            $this->fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (TaskFailureError $exception) {
            $this->assertSame("Error", $exception->getOriginalClassName());
            $this->assertGreaterThan(0, \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class)));
        }

        $worker->shutdown();
    }

    public function testUnserializableTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                public function run(Environment $environment, CancellationToken $token)
                {
                }
            });
            $this->fail("Tasks that cannot be serialized should throw an exception");
        } catch (SerializationException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "The given data could not be serialized"));
        }

        $worker->shutdown();
    }

    public function testUnserializableResult()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\UnserializableResultTask);
            $this->fail("Tasks results that cannot be serialized should throw an exception");
        } catch (TaskFailureException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "Uncaught Amp\Serialization\SerializationException in worker"));
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableResult()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\NonAutoloadableResultTask);
            $this->fail("Tasks results that cannot be autoloaded should throw an exception");
        } catch (\Error $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "Class instances returned from Amp\Parallel\Worker\Task::run() must be autoloadable by the Composer autoloader"));
        }

        $worker->shutdown();
    }

    public function testUnserializableTaskFollowedByValidTask()
    {
        $worker = $this->createWorker();

        $promise1 = async(fn() => $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
            public function run(Environment $environment, CancellationToken $token)
            {
            }
        }));
        $promise2 = async(fn() => $worker->enqueue(new Fixtures\TestTask(42)));

        $this->assertSame(42, await($promise2));

        $worker->shutdown();
    }

    public function testCustomAutoloader()
    {
        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/custom-bootstrap.php');

        $this->assertTrue($worker->enqueue(new Fixtures\AutoloadTestTask));

        $worker->shutdown();
    }

    public function testInvalidCustomAutoloader()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('No file found at bootstrap file path given');

        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/not-found.php');

        $worker->enqueue(new Fixtures\AutoloadTestTask);

        $worker->shutdown();
    }

    public function testCancellableTask()
    {
        $this->expectException(TaskCancelledException::class);

        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellationToken(100));
        } finally {
            $worker->shutdown();
        }
    }

    public function testEnqueueAfterCancelledTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellationToken(100));
            $this->fail(TaskCancelledException::class . ' did not fail enqueue promise');
        } catch (TaskCancelledException $exception) {
            // Task should be cancelled, ignore this exception.
        }

        $this->assertTrue($worker->enqueue(new Fixtures\ConstantTask));

        $worker->shutdown();
    }

    public function testCancellingCompletedTask()
    {
        $worker = $this->createWorker();

        $this->assertTrue($worker->enqueue(new Fixtures\ConstantTask(), new TimeoutCancellationToken(100)));

        $worker->shutdown();
    }
}
