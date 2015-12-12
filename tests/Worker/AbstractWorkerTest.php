<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Awaitable;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

abstract class AbstractWorkerTest extends TestCase
{
    /**
     * @return \Icicle\Concurrent\Worker\WorkerFactory
     */
    abstract protected function getFactory();

    public function testIsRunning()
    {
        Coroutine\create(function () {
            $worker = $this->getFactory()->create();
            $this->assertFalse($worker->isRunning());

            $worker->start();
            $this->assertTrue($worker->isRunning());

            yield $worker->shutdown();
            $this->assertFalse($worker->isRunning());
        })->done();

        Loop\run();
    }

    public function testIsIdleOnStart()
    {
        Coroutine\create(function () {
            $worker = $this->getFactory()->create();
            $worker->start();

            $this->assertTrue($worker->isIdle());

            yield $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testEnqueue()
    {
        Coroutine\create(function () {
            $worker = $this->getFactory()->create();
            $worker->start();

            $returnValue = (yield $worker->enqueue(new TestTask(42)));
            $this->assertEquals(42, $returnValue);

            yield $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testEnqueueMultiple()
    {
        Coroutine\create(function () {
            $worker = $this->getFactory()->create();
            $worker->start();

            $values = (yield Awaitable\all([
                new Coroutine\Coroutine($worker->enqueue(new TestTask(42))),
                new Coroutine\Coroutine($worker->enqueue(new TestTask(56))),
                new Coroutine\Coroutine($worker->enqueue(new TestTask(72)))
            ]));

            $this->assertEquals([42, 56, 72], $values);

            yield $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testNotIdleOnEnqueue()
    {
        Coroutine\create(function () {
            $worker = $this->getFactory()->create();
            $worker->start();

            $coroutine = new Coroutine\Coroutine($worker->enqueue(new TestTask(42)));
            $this->assertFalse($worker->isIdle());
            yield $coroutine;

            yield $worker->shutdown();
        })->done();

        Loop\run();
    }

    public function testKill()
    {
        $worker = $this->getFactory()->create();
        $worker->start();

        $this->assertRunTimeLessThan([$worker, 'kill'], 0.2);
        $this->assertFalse($worker->isRunning());
    }
}
