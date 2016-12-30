<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Test\TestCase;

abstract class AbstractWorkerTest extends TestCase {
    /**
     * @return \Amp\Parallel\Worker\Worker
     */
    abstract protected function createWorker();

    public function testIsRunning() {
        \Amp\execute(function () {
            $worker = $this->createWorker();
            $this->assertFalse($worker->isRunning());

            $worker->start();
            $this->assertTrue($worker->isRunning());

            yield $worker->shutdown();
            $this->assertFalse($worker->isRunning());
        });

    }

    public function testIsIdleOnStart() {
        \Amp\execute(function () {
            $worker = $this->createWorker();
            $worker->start();

            $this->assertTrue($worker->isIdle());

            yield $worker->shutdown();
        });

    }

    public function testEnqueue() {
        \Amp\execute(function () {
            $worker = $this->createWorker();
            $worker->start();

            $returnValue = yield $worker->enqueue(new TestTask(42));
            $this->assertEquals(42, $returnValue);

            yield $worker->shutdown();
        });

    }

    public function testEnqueueMultiple() {
        \Amp\execute(function () {
            $worker = $this->createWorker();
            $worker->start();
    
            $values = yield \Amp\all([
                $worker->enqueue(new TestTask(42)),
                $worker->enqueue(new TestTask(56)),
                $worker->enqueue(new TestTask(72))
            ]);

            $this->assertEquals([42, 56, 72], $values);

            yield $worker->shutdown();
        });

    }

    public function testNotIdleOnEnqueue() {
        \Amp\execute(function () {
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

        $this->assertRunTimeLessThan([$worker, 'kill'], 0.2);
        $this->assertFalse($worker->isRunning());
    }
}
