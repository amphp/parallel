<?php

namespace Amp\Tests\Concurrent\Worker;

use Amp\Awaitable;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Tests\Concurrent\TestCase;

abstract class AbstractWorkerTest extends TestCase
{
    /**
     * @return \Amp\Concurrent\Worker\Worker
     */
    abstract protected function createWorker();

    public function testIsRunning()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $this->assertFalse($worker->isRunning());

            $worker->start();
            $this->assertTrue($worker->isRunning());

            yield from $worker->shutdown();
            $this->assertFalse($worker->isRunning());
        })->done();

        Loop\run();
    }

    public function testIsIdleOnStart()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $worker->start();

            $this->assertTrue($worker->isIdle());

            yield from $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testEnqueue()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $worker->start();

            $returnValue = yield from $worker->enqueue(new TestTask(42));
            $this->assertEquals(42, $returnValue);

            yield from $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testEnqueueMultiple()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $worker->start();

            $values = yield Awaitable\all([
                new Coroutine\Coroutine($worker->enqueue(new TestTask(42))),
                new Coroutine\Coroutine($worker->enqueue(new TestTask(56))),
                new Coroutine\Coroutine($worker->enqueue(new TestTask(72)))
            ]);

            $this->assertEquals([42, 56, 72], $values);

            yield from $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testNotIdleOnEnqueue()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $worker->start();

            $coroutine = new Coroutine\Coroutine($worker->enqueue(new TestTask(42)));
            $this->assertFalse($worker->isIdle());
            yield $coroutine;

            yield from $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testKill()
    {
        $worker = $this->createWorker();
        $worker->start();

        $this->assertRunTimeLessThan([$worker, 'kill'], 0.2);
        $this->assertFalse($worker->isRunning());
    }
}
