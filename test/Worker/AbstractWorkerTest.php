<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\DefaultWorkerFactory;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskCancelledException;
use Amp\Parallel\Worker\TaskFailureError;
use Amp\Parallel\Worker\TaskFailureException;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Amp\TimeoutCancellation;
use function Amp\async;
use function Amp\delay;

class NonAutoloadableTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): int
    {
        return 1;
    }
}

abstract class AbstractWorkerTest extends AsyncTestCase
{
    public function testWorkerConstantDefined()
    {
        $worker = $this->createWorker();
        self::assertTrue($worker->enqueue(new Fixtures\ConstantTask)->getFuture()->await());
        $worker->shutdown();
    }

    public function testIsRunning()
    {
        $worker = $this->createWorker();
        self::assertTrue($worker->isRunning());

        $worker->shutdown();
        self::assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart()
    {
        $worker = $this->createWorker();

        self::assertTrue($worker->isIdle());

        $worker->shutdown();
    }

    public function testEnqueueShouldThrowStatusError()
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('shut down');

        $worker = $this->createWorker();

        self::assertTrue($worker->isIdle());

        $worker->shutdown();
        $worker->enqueue(new Fixtures\TestTask(42));
    }

    public function testEnqueue()
    {
        $worker = $this->createWorker();

        $returnValue = $worker->enqueue(new Fixtures\TestTask(42))->getFuture()->await();
        self::assertEquals(42, $returnValue);

        $worker->shutdown();
    }

    public function testEnqueueMultipleSynchronous()
    {
        $worker = $this->createWorker();

        $futures = [
            $worker->enqueue(new Fixtures\TestTask(42))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(56))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(72))->getFuture(),
        ];

        self::assertEquals([42, 56, 72], Future\all($futures));

        $worker->shutdown();
    }

    public function testEnqueueMultipleAsynchronous()
    {
        $this->setTimeout(0.5);

        $worker = $this->createWorker();

        $futures = [
            $worker->enqueue(new Fixtures\TestTask(42, 0.2))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(56, 0.3))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(72, 0.1))->getFuture(),
        ];

        self::assertEquals([2 => 72, 0 => 42, 1 => 56], Future\all($futures));

        $worker->shutdown();
    }

    public function testEnqueueMultipleThenShutdown()
    {
        $this->setTimeout(0.5);

        $worker = $this->createWorker();

        $futures = [
            $worker->enqueue(new Fixtures\TestTask(42, 0.2))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(56, 0.3))->getFuture(),
            $worker->enqueue(new Fixtures\TestTask(72, 0.1))->getFuture(),
        ];

        // Send shutdown signal, but don't await until tasks have finished.
        $shutdown = async(fn () => $worker->shutdown());

        self::assertEquals([2 => 72, 0 => 42, 1 => 56], Future\all($futures));

        $shutdown->await(); // Await shutdown before ending test.
    }

    public function testNotIdleOnEnqueue()
    {
        $worker = $this->createWorker();

        $future = $worker->enqueue(new Fixtures\TestTask(42))->getFuture();
        delay(0); // Tick event loop to call Worker::enqueue()
        self::assertFalse($worker->isIdle());
        $future->await();

        $worker->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(500);

        $worker = $this->createWorker();

        $job = $worker->enqueue(new Fixtures\TestTask(42));
        $job->getFuture()->ignore();

        $worker->kill();

        self::assertFalse($worker->isRunning());
    }

    public function testFailingTaskWithException()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Exception::class))->getFuture()->await();
        } catch (TaskFailureException $exception) {
            self::assertSame(\Exception::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithError()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Error::class))->getFuture()->await();
        } catch (TaskFailureError $exception) {
            self::assertSame(\Error::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithPreviousException()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\FailingTask(\Error::class, \Exception::class))->getFuture()->await();
        } catch (TaskFailureError $exception) {
            self::assertSame(\Error::class, $exception->getOriginalClassName());
            $previous = $exception->getPrevious();
            self::assertInstanceOf(TaskFailureException::class, $previous);
            self::assertSame(\Exception::class, $previous->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new NonAutoloadableTask)->getFuture()->await();
            self::fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (TaskFailureError $exception) {
            self::assertSame("Error", $exception->getOriginalClassName());
            self::assertGreaterThan(
                0,
                \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class))
            );
        }

        $worker->shutdown();
    }

    public function testUnserializableTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
                {
                }
            })->getFuture()->await();
            self::fail("Tasks that cannot be serialized should throw an exception");
        } catch (SerializationException $exception) {
            self::assertSame(0, \strpos($exception->getMessage(), "The given data could not be serialized"));
        }

        $worker->shutdown();
    }

    public function testUnserializableResult()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\UnserializableResultTask)->getFuture()->await();
            self::fail("Tasks results that cannot be serialized should throw an exception");
        } catch (TaskFailureException $exception) {
            self::assertSame(
                0,
                \strpos($exception->getMessage(), "Uncaught Amp\Serialization\SerializationException in worker")
            );
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableResult()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\NonAutoloadableResultTask)->getFuture()->await();
            self::fail("Tasks results that cannot be autoloaded should throw an exception");
        } catch (\Error $exception) {
            self::assertSame(0, \strpos(
                $exception->getMessage(),
                "Class instances returned from Amp\Parallel\Worker\Task::run() must be autoloadable by the Composer autoloader"
            ));
        }

        $worker->shutdown();
    }

    public function testUnserializableTaskFollowedByValidTask()
    {
        $worker = $this->createWorker();

        async(fn () => $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
            public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
            {
                return null;
            }
        }))->ignore();

        $future = $worker->enqueue(new Fixtures\TestTask(42))->getFuture();

        self::assertSame(42, $future->await());

        $worker->shutdown();
    }

    public function testCustomAutoloader()
    {
        $worker = $this->createWorker(autoloadPath: __DIR__ . '/Fixtures/custom-bootstrap.php');

        self::assertTrue($worker->enqueue(new Fixtures\AutoloadTestTask)->getFuture()->await());

        $worker->shutdown();
    }

    public function testInvalidCustomAutoloader()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('No file found at bootstrap path given');

        $worker = $this->createWorker(autoloadPath: __DIR__ . '/Fixtures/not-found.php');

        $worker->enqueue(new Fixtures\AutoloadTestTask)->getFuture()->await();

        $worker->shutdown();
    }

    public function testCancellableTask()
    {
        $this->expectException(TaskCancelledException::class);

        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellation(0.1))->getFuture()->await();
        } finally {
            $worker->shutdown();
        }
    }

    public function testEnqueueAfterCancelledTask()
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new Fixtures\CancellingTask, new TimeoutCancellation(0.1))->getFuture()->await();
            self::fail(TaskCancelledException::class . ' did not fail enqueue future');
        } catch (TaskCancelledException $exception) {
            // Task should be cancelled, ignore this exception.
        }

        self::assertTrue($worker->enqueue(new Fixtures\ConstantTask)->getFuture()->await());

        $worker->shutdown();
    }

    public function testCancellingCompletedTask()
    {
        $worker = $this->createWorker();

        self::assertTrue($worker->enqueue(
            new Fixtures\ConstantTask(),
            new TimeoutCancellation(0.1),
        )->getFuture()->await());

        $worker->shutdown();
    }

    protected function createWorker(string $cacheClass = LocalCache::class, ?string $autoloadPath = null): Worker
    {
        $factory = new DefaultWorkerFactory(
            cacheClass: $cacheClass,
            bootstrapPath: $autoloadPath,
            contextFactory: $this->createContextFactory(),
        );

        return $factory->create();
    }

    abstract protected function createContextFactory(): ContextFactory;
}
