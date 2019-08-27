<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\PanicError;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\BasicEnvironment;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\WorkerException;
use Amp\PHPUnit\AsyncTestCase;

class NonAutoloadableTask implements Task
{
    public function run(Environment $environment)
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

    public function testWorkerConstantDefined()
    {
        $worker = $this->createWorker();
        $this->assertTrue(yield $worker->enqueue(new Fixtures\ConstantTask));
        yield $worker->shutdown();
    }

    public function testIsRunning()
    {
        $worker = $this->createWorker();
        $this->assertTrue($worker->isRunning());

        $worker->enqueue(new Fixtures\TestTask(42)); // Enqueue a task to start the worker.

        $this->assertTrue($worker->isRunning());

        yield $worker->shutdown();
        $this->assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart()
    {
        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        yield $worker->shutdown();
    }

    public function testEnqueueShouldThrowStatusError()
    {
        $this->expectException(StatusError::class);
        $this->expectExceptionMessage('The worker has been shut down');

        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        yield $worker->shutdown();
        yield $worker->enqueue(new Fixtures\TestTask(42));
    }

    public function testEnqueue()
    {
        $worker = $this->createWorker();

        $returnValue = yield $worker->enqueue(new Fixtures\TestTask(42));
        $this->assertEquals(42, $returnValue);

        yield $worker->shutdown();
    }

    public function testEnqueueMultipleSynchronous()
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

    public function testEnqueueMultipleAsynchronous()
    {
        $worker = $this->createWorker();

        $promises = [
                $worker->enqueue(new Fixtures\TestTask(42, 200)),
                $worker->enqueue(new Fixtures\TestTask(56, 300)),
                $worker->enqueue(new Fixtures\TestTask(72, 100))
            ];

        $expected = [42, 56, 72];
        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) use (&$expected) {
                $this->assertSame(\array_shift($expected), $v);
            });
        }

        yield $promises; // Wait until all tasks have finished before invoking $worker->shutdown().

        yield $worker->shutdown();
    }

    public function testEnqueueMultipleThenShutdown()
    {
        $worker = $this->createWorker();

        $promises = [
                $worker->enqueue(new Fixtures\TestTask(42, 200)),
                $worker->enqueue(new Fixtures\TestTask(56, 300)),
                $worker->enqueue(new Fixtures\TestTask(72, 100))
            ];

        yield $worker->shutdown();

        \array_shift($promises); // First task will succeed.

        foreach ($promises as $promise) {
            $promise->onResolve(function ($e, $v) {
                $this->assertInstanceOf(WorkerException::class, $e);
            });
        }
    }

    public function testNotIdleOnEnqueue()
    {
        $worker = $this->createWorker();

        $coroutine = $worker->enqueue(new Fixtures\TestTask(42));
        $this->assertFalse($worker->isIdle());
        yield $coroutine;

        yield $worker->shutdown();
    }

    public function testKill()
    {
        $this->setTimeout(250);


        $worker = $this->createWorker();

        $worker->enqueue(new Fixtures\TestTask(42));

        $worker->kill();

        $this->assertFalse($worker->isRunning());
    }

    public function testFailingTaskWithException()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Exception::class));
        } catch (TaskException $exception) {
            $this->assertSame(\Exception::class, $exception->getName());
        }

        yield $worker->shutdown();
    }

    public function testFailingTaskWithError()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Error::class));
        } catch (TaskError $exception) {
            $this->assertSame(\Error::class, $exception->getName());
        }

        yield $worker->shutdown();
    }

    public function testFailingTaskWithPreviousException()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\FailingTask(\Error::class, \Exception::class));
        } catch (TaskError $exception) {
            $this->assertSame(\Error::class, $exception->getName());
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(TaskException::class, $previous);
            $this->assertSame(\Exception::class, $previous->getName());
        }

        yield $worker->shutdown();
    }

    public function testNonAutoloadableTask()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new NonAutoloadableTask);
            $this->fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (TaskError $exception) {
            $this->assertSame("Error", $exception->getName());
            $this->assertGreaterThan(0, \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class)));
        }

        yield $worker->shutdown();
    }

    public function testUnserializableTask()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
                public function run(Environment $environment)
                {
                }
            });
            $this->fail("Tasks that cannot be serialized should throw an exception");
        } catch (SerializationException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "The given data cannot be sent because it is not serializable"));
        }

        yield $worker->shutdown();
    }

    public function testUnserializableResult()
    {
        $worker = $this->createWorker();

        try {
            yield $worker->enqueue(new Fixtures\UnserializableResultTask);
            $this->fail("Tasks results that cannot be serialized should throw an exception");
        } catch (TaskException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "Uncaught Amp\Parallel\Sync\SerializationException in worker"));
        }

        yield $worker->shutdown();
    }

    public function testNonAutoloadableResult()
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

    public function testUnserializableTaskFollowedByValidTask()
    {
        $worker = $this->createWorker();

        $promise1 = $worker->enqueue(new class implements Task { // Anonymous classes are not serializable.
            public function run(Environment $environment)
            {
            }
        });
        $promise2 = $worker->enqueue(new Fixtures\TestTask(42));

        $this->assertSame(42, yield $promise2);

        yield $worker->shutdown();
    }

    public function testCustomAutoloader()
    {
        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/custom-bootstrap.php');

        $this->assertTrue(yield $worker->enqueue(new Fixtures\AutoloadTestTask));

        yield $worker->shutdown();
    }

    public function testInvalidCustomAutoloader()
    {
        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('No file found at bootstrap file path given');

        $worker = $this->createWorker(BasicEnvironment::class, __DIR__ . '/Fixtures/not-found.php');

        $this->assertTrue(yield $worker->enqueue(new Fixtures\AutoloadTestTask));

        yield $worker->shutdown();
    }
}
