<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Failure;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerException;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;

class DefaultPoolTest extends AsyncTestCase
{
    public function testFactoryCreatesStoppedWorker(): \Generator
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('isRunning')
            ->willReturn(false);

        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')
            ->willReturn($worker);

        $pool = new DefaultPool(32, $factory);

        $this->expectException(WorkerException::class);
        $this->expectExceptionMessage('Worker factory did not create a viable worker');

        yield $pool->enqueue($this->createMock(Task::class));
    }

    /**
     * @requires PHPUnit >= 8.4
     */
    public function testCrashedWorker(): \Generator
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('isRunning')
            ->willReturnOnConsecutiveCalls(true, false);
        $worker->method('shutdown')
            ->willReturn(new Failure(new WorkerException('Worker unexpectedly exited')));
        $worker->method('enqueue')
            ->willReturn(new Success);

        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')
            ->willReturn($worker);

        $pool = new DefaultPool(32, $factory);

        yield $pool->enqueue($this->createMock(Task::class));

        $this->expectWarning();
        $this->expectWarningMessage('Worker in pool crashed');

        yield $pool->enqueue($this->createMock(Task::class));
    }
}
