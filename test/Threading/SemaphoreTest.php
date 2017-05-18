<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Loop;
use Amp\Parallel\Sync\Semaphore as SyncSemaphore;
use Amp\Parallel\Test\Sync\AbstractSemaphoreTest;
use Amp\Parallel\Threading\Semaphore;
use Amp\Parallel\Threading\Thread;

/**
 * @group threading
 * @requires extension pthreads
 */
class SemaphoreTest extends AbstractSemaphoreTest {
    public function createSemaphore(int $locks) {
        return new Semaphore($locks);
    }

    public function testAcquireInMultipleThreads() {
        Loop::run(function () {
            $this->semaphore = $this->createSemaphore(1);

            $thread1 = new Thread(function (SyncSemaphore $semaphore) {
                $lock = yield $semaphore->acquire();

                usleep(100000);

                $lock->release();

                return 0;
            }, $this->semaphore);

            $thread2 = new Thread(function (SyncSemaphore $semaphore) {
                $lock = yield $semaphore->acquire();

                usleep(100000);

                $lock->release();

                return 1;
            }, $this->semaphore);

            $start = microtime(true);

            $thread1->start();
            $thread2->start();

            yield $thread1->join();
            yield $thread2->join();

            $this->assertGreaterThan(0.1, microtime(true) - $start);
        });
    }
}
