<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

abstract class AbstractWorkerTest extends TestCase
{
    /**
     * @return \Icicle\Concurrent\Worker\WorkerInterface
     */
    abstract protected function createWorker();

    public function testIsRunning()
    {
        $worker = $this->createWorker();
        $this->assertFalse($worker->isRunning());

        $worker->start();
        $this->assertTrue($worker->isRunning());

        $worker->kill();
        sleep(1);
        $this->assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart()
    {
        $worker = $this->createWorker();
        $worker->start();

        $this->assertTrue($worker->isIdle());

        $worker->kill();
    }

    public function testEnqueue()
    {
        Coroutine\create(function () {
            $worker = $this->createWorker();
            $worker->start();

            $returnValue = (yield $worker->enqueue(new TestTask(42)));
            $this->assertEquals(42, $returnValue);

            yield $worker->shutdown();
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

            yield $worker->shutdown();
        })->done();

        Loop\run();
    }
}
