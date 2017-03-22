<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Loop;
use Amp\Parallel\Threading\Mutex;
use Amp\PHPUnit\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class MutexTest extends TestCase {
    public function testAcquire() {
        Loop::run(function () {
            $mutex = new Mutex;
            $lock = yield $mutex->acquire();
            $lock->release();
            $this->assertTrue($lock->isReleased());
        });

    }

    public function testAcquireMultiple() {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $mutex = new Mutex;

                $lock1 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock3) {
                    $lock3->release();
                });
            });
        }, 1500);
    }
}
