<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Test\Worker\Fixtures\CommunicatingTask;
use Amp\Parallel\Worker\ContextWorkerFactory;
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
    public function run(Channel $channel, Cancellation $cancellation): int
    {
        return 1;
    }
}

abstract class AbstractWorkerTest extends AsyncTestCase
{
    public function testWorkerConstantDefined(): void
    {
        $worker = $this->createWorker();
        self::assertTrue($worker->submit(new Fixtures\ConstantTask)->getResult()->await());
        $worker->shutdown();
    }

    public function testIsRunning(): void
    {
        $worker = $this->createWorker();
        self::assertTrue($worker->isRunning());

        $worker->shutdown();
        self::assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart(): void
    {
        $worker = $this->createWorker();

        self::assertTrue($worker->isIdle());

        $worker->shutdown();
    }

    public function testSubmitShouldThrowStatusError(): void
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('shut down');

        $worker = $this->createWorker();

        self::assertTrue($worker->isIdle());

        $worker->shutdown();
        $worker->submit(new Fixtures\TestTask(42));
    }

    public function testSubmit(): void
    {
        $worker = $this->createWorker();

        $returnValue = $worker->submit(new Fixtures\TestTask(42))->getResult()->await();
        self::assertEquals(42, $returnValue);

        $worker->shutdown();
    }

    public function testSubmitMultipleSynchronous(): void
    {
        $worker = $this->createWorker();

        $futures = [
            $worker->submit(new Fixtures\TestTask(42))->getResult(),
            $worker->submit(new Fixtures\TestTask(56))->getResult(),
            $worker->submit(new Fixtures\TestTask(72))->getResult(),
        ];

        self::assertEquals([42, 56, 72], Future\await($futures));

        $worker->shutdown();
    }

    public function testSubmitMultipleAsynchronous(): void
    {
        $this->setTimeout(5);

        $worker = $this->createWorker();

        $futures = [
            $worker->submit(new Fixtures\TestTask(42, 2))->getResult(),
            $worker->submit(new Fixtures\TestTask(56, 3))->getResult(),
            $worker->submit(new Fixtures\TestTask(72, 1))->getResult(),
        ];

        self::assertEquals([2 => 72, 0 => 42, 1 => 56], Future\await($futures));

        $worker->shutdown();
    }

    public function testSubmitMultipleThenShutdown(): void
    {
        $this->setTimeout(5);

        $worker = $this->createWorker();

        $futures = [
            $worker->submit(new Fixtures\TestTask(42, 2))->getResult(),
            $worker->submit(new Fixtures\TestTask(56, 3))->getResult(),
            $worker->submit(new Fixtures\TestTask(72, 1))->getResult(),
        ];

        // Send shutdown signal, but don't await until tasks have finished.
        $shutdown = async(fn () => $worker->shutdown());

        self::assertEquals([2 => 72, 0 => 42, 1 => 56], Future\await($futures));

        $shutdown->await(); // Await shutdown before ending test.
    }

    public function testNotIdleOnSubmit(): void
    {
        $worker = $this->createWorker();

        $future = $worker->submit(new Fixtures\TestTask(42))->getResult();
        delay(0); // Tick event loop to call Worker::submit()
        self::assertFalse($worker->isIdle());
        $future->await();

        $worker->shutdown();
    }

    public function testKill(): void
    {
        $this->setTimeout(500);

        $worker = $this->createWorker();

        $job = $worker->submit(new Fixtures\TestTask(42));
        $job->getResult()->ignore();

        $worker->kill();

        self::assertFalse($worker->isRunning());
    }

    public function testFailingTaskWithException(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\FailingTask(\Exception::class))->getResult()->await();
        } catch (TaskFailureException $exception) {
            self::assertSame(\Exception::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithError(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\FailingTask(\Error::class))->getResult()->await();
        } catch (TaskFailureError $exception) {
            self::assertSame(\Error::class, $exception->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testFailingTaskWithPreviousException(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\FailingTask(\Error::class, \Exception::class))->getResult()->await();
        } catch (TaskFailureError $exception) {
            self::assertSame(\Error::class, $exception->getOriginalClassName());
            $previous = $exception->getPrevious();
            self::assertInstanceOf(TaskFailureException::class, $previous);
            self::assertSame(\Exception::class, $previous->getOriginalClassName());
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableTask(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new NonAutoloadableTask)->getResult()->await();
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

    public function testUnserializableTask(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new class implements Task { // Anonymous classes are not serializable.
                public function run(Channel $channel, Cancellation $cancellation): mixed
                {
                    return null;
                }
            })->getResult()->await();
            self::fail("Tasks that cannot be serialized should throw an exception");
        } catch (SerializationException $exception) {
            self::assertSame(0, \strpos($exception->getMessage(), "The given data could not be serialized"));
        }

        $worker->shutdown();
    }

    public function testUnserializableResult(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\UnserializableResultTask)->getResult()->await();
            self::fail("Tasks results that cannot be serialized should throw an exception");
        } catch (TaskFailureException $exception) {
            self::assertSame(
                0,
                \strpos($exception->getMessage(), "Amp\Serialization\SerializationException thrown in context")
            );
        }

        $worker->shutdown();
    }

    public function testNonAutoloadableResult(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\NonAutoloadableResultTask)->getResult()->await();
            self::fail("Tasks results that cannot be autoloaded should throw an exception");
        } catch (\Error $exception) {
            self::assertSame(0, \strpos(
                $exception->getMessage(),
                "Class instances returned from Amp\Parallel\Worker\Task::run() must be autoloadable by the Composer autoloader"
            ));
        }

        $worker->shutdown();
    }

    public function testUnserializableTaskFollowedByValidTask(): void
    {
        $worker = $this->createWorker();

        async(fn () => $worker->submit(new class implements Task { // Anonymous classes are not serializable.
            public function run(Channel $channel, Cancellation $cancellation): mixed
            {
                return null;
            }
        }))->ignore();

        $future = $worker->submit(new Fixtures\TestTask(42))->getResult();

        self::assertSame(42, $future->await());

        $worker->shutdown();
    }

    public function testCustomAutoloader(): void
    {
        $worker = $this->createWorker(autoloadPath: __DIR__ . '/Fixtures/custom-bootstrap.php');

        self::assertTrue($worker->submit(new Fixtures\AutoloadTestTask)->getResult()->await());

        $worker->shutdown();
    }

    public function testInvalidCustomAutoloader(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('No file found at bootstrap path given');

        $worker = $this->createWorker(autoloadPath: __DIR__ . '/Fixtures/not-found.php');

        $worker->submit(new Fixtures\AutoloadTestTask)->getResult()->await();

        $worker->shutdown();
    }

    public function testCancellableTask(): void
    {
        $this->expectException(TaskCancelledException::class);

        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\CancellingTask, new TimeoutCancellation(0.1))->getResult()->await();
        } finally {
            $worker->shutdown();
        }
    }

    public function testSubmitAfterCancelledTask(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->submit(new Fixtures\CancellingTask, new TimeoutCancellation(0.1))->getResult()->await();
            self::fail(TaskCancelledException::class . ' did not fail submit future');
        } catch (TaskCancelledException $exception) {
            // Task should be cancelled, ignore this exception.
        }

        self::assertTrue($worker->submit(new Fixtures\ConstantTask)->getResult()->await());

        $worker->shutdown();
    }

    public function testCancellingCompletedTask(): void
    {
        $worker = $this->createWorker();

        self::assertTrue($worker->submit(
            new Fixtures\ConstantTask(),
            new TimeoutCancellation(0.1),
        )->getResult()->await());

        $worker->shutdown();
    }

    public function testCommunicatingJob(): void
    {
        $worker = $this->createWorker();

        $cancellation = new TimeoutCancellation(1);
        $execution = $worker->submit(new CommunicatingTask(), $cancellation);

        $channel = $execution->getChannel();

        self::assertSame('test', $channel->receive($cancellation));
        $channel->send('out');

        self::assertSame('out', $execution->getResult()->await($cancellation));
    }

    protected function createWorker(?string $autoloadPath = null): Worker
    {
        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: $this->createContextFactory(),
        );

        return $factory->create();
    }

    abstract protected function createContextFactory(): ContextFactory;
}
