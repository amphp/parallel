<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Threading;

use Amp\Concurrent\Threading\Mutex;
use Amp\Concurrent\Test\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class MutexTest extends TestCase {
    public function testAcquire() {
        \Amp\execute(function () {
            $mutex = new Mutex();
            $lock = yield $mutex->acquire();
            $lock->release();
            $this->assertTrue($lock->isReleased());
        });

    }

    public function testAcquireMultiple() {
        $this->assertRunTimeGreaterThan(function () {
            \Amp\execute(function () {
                $mutex = new Mutex();

                $lock1 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock3) {
                    $lock3->release();
                });
            });
        }, 1.5);
    }
}
