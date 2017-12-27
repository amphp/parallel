<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\WorkerProcess;
use Amp\Promise;

/**
 * @group process
 */
class ProcessPoolTest extends AbstractPoolTest {
    protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->method('create')->will($this->returnCallback(function () {
            return new WorkerProcess;
        }));

        return new DefaultPool($max, $factory);
    }

    /**
     * @FIXME This test should be moved to AbstractPoolTest once the GC issues with pthreads are resolved.
     */
    public function testCleanGarbageCollection() {
        // See https://github.com/amphp/parallel-functions/issues/5
        Loop::run(function () {
            for ($i = 0; $i < 15; $i++) {
                $pool = $this->createPool(32);

                $values = \range(1, 50);
                $tasks = \array_map(function (int $value): Task {
                    return new TestTask($value);
                }, $values);

                $promises = \array_map(function (Task $task) use ($pool): Promise {
                    return $pool->enqueue($task);
                }, $tasks);

                $this->assertSame($values, yield $promises);
            }
        });
    }
}
