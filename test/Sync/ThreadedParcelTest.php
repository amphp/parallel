<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Loop;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ThreadedParcel;

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

            $thread = yield Thread::run(function (Channel $channel, ThreadedParcel $parcel) {
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
