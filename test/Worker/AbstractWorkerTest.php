<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskError;
use Amp\PHPUnit\TestCase;

class NonAutoloadableTask implements Task {
    public function run(Environment $environment) {
        return 1;
    }
}

abstract class AbstractWorkerTest extends TestCase {
    /**
     * @return \Amp\Parallel\Worker\Worker
     */
    abstract protected function createWorker();

    public function testWorkerConstantDefined() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();
            $this->assertTrue(yield $worker->enqueue(new ConstantTask));
            yield $worker->shutdown();
        });
    }

    public function testIsRunning() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $this->assertFalse($worker->isRunning());

            $worker->start();
            $this->assertTrue($worker->isRunning());

            yield $worker->shutdown();
            $this->assertFalse($worker->isRunning());
        });
    }

    public function testIsIdleOnStart() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

            $this->assertTrue($worker->isIdle());

            yield $worker->shutdown();
        });
    }

    public function testEnqueue() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

            $returnValue = yield $worker->enqueue(new TestTask(42));
            $this->assertEquals(42, $returnValue);

            yield $worker->shutdown();
        });
    }

    public function testEnqueueMultipleSynchronous() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

            $values = yield \Amp\Promise\all([
                $worker->enqueue(new TestTask(42)),
                $worker->enqueue(new TestTask(56)),
                $worker->enqueue(new TestTask(72))
            ]);

            $this->assertEquals([42, 56, 72], $values);

            yield $worker->shutdown();
        });
    }

    public function testEnqueueMultipleAsynchronous() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

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

            yield $worker->shutdown();
        });
    }

    public function testNotIdleOnEnqueue() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

            $coroutine = $worker->enqueue(new TestTask(42));
            $this->assertFalse($worker->isIdle());
            yield $coroutine;

            yield $worker->shutdown();
        });
    }

    public function testKill() {
        $worker = $this->createWorker();
        $worker->start();

        $this->assertRunTimeLessThan([$worker, 'kill'], 250);
        $this->assertFalse($worker->isRunning());
    }

    public function testUnserializableTask() {
        Loop::run(function () {
            $worker = $this->createWorker();
            $worker->start();

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
}
