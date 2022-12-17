<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Future;
use Amp\Parallel\Worker\DefaultWorkerPool;
use Amp\Parallel\Worker\Execution;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerException;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\Channel;

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

        $pool = new DefaultWorkerPool(32, $factory);

        $this->expectException(WorkerException::class);
        $this->expectExceptionMessage('Worker factory did not create a viable worker');

        $pool->submit($this->createMock(Task::class));
    }

    public function testCrashedWorker(): void
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->expects(self::exactly(2))
            ->method('create')
            ->willReturnCallback(function (): Worker {
                $worker = $this->createMock(Worker::class);
                $worker->method('isRunning')
                    ->willReturnOnConsecutiveCalls(true, false, false);
                $worker->method('shutdown')
                    ->willThrowException(new WorkerException('Test worker unexpectedly exited'));
                $worker->method('submit')
                    ->willReturn(new Execution(
                        $this->createMock(Task::class),
                        $this->createMock(Channel::class),
                        Future::complete(),
                    ));

                return $worker;
            });

        $pool = new DefaultWorkerPool(32, $factory);

        $pool->submit($this->createMock(Task::class))->getResult()->await();

        $pool->submit($this->createMock(Task::class))->getResult()->await();
    }
}
