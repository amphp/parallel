<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Future;
use Amp\Sync\Channel;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Job;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerException;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\UnhandledException;

class DefaultPoolTest extends AsyncTestCase
{
    public function testFactoryCreatesStoppedWorker(): void
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

        $pool->enqueue($this->createMock(Task::class));
    }

    public function testCrashedWorker(): void
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->expects(self::exactly(2))
            ->method('create')
            ->willReturnCallback(function (): Worker {
                $worker = $this->createMock(Worker::class);
                $worker->method('isRunning')
                    ->willReturnOnConsecutiveCalls(true, false);
                $worker->method('shutdown')
                    ->willThrowException(new WorkerException('Test worker unexpectedly exited'));
                $worker->method('enqueue')
                    ->willReturn(new Job(
                        $this->createMock(Task::class),
                        $this->createMock(Channel::class),
                        Future::complete(),
                    ));

                return $worker;
            });

        $pool = new DefaultPool(32, $factory);

        $pool->enqueue($this->createMock(Task::class))->getFuture()->await();

        // Warning is forwarded as an exception to loop.
        $this->expectException(UnhandledException::class);

        $pool->enqueue($this->createMock(Task::class))->getFuture()->await();
    }
}
