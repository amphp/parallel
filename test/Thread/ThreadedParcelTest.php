<?php

namespace Amp\Parallel\Test\Thread;

use Amp\Loop;
use Amp\Parallel\Test\Sync\AbstractParcelTest;
use Amp\Parallel\Thread\Thread;
use Amp\Parallel\Thread\ThreadedParcel;

/**
 * @requires extension pthreads
 */
class ThreadedParcelTest extends AbstractParcelTest {
    protected function createParcel($value) {
        return new ThreadedParcel($value);
    }

    public function testWithinThread() {
        Loop::run(function () {
            $value = 1;
            $parcel = new ThreadedParcel($value);

            $thread = Thread::spawn(function (ThreadedParcel $parcel) {
                $parcel->synchronized(function (int $value) {
                    return $value + 1;
                });
                return 0;
            }, $parcel);

            $this->assertSame(0, yield $thread->join());
            $this->assertSame($value + 1, yield $parcel->unwrap());
        });
    }
}
