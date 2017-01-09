<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Parallel\Threading\Thread;
use Amp\Parallel\Test\AbstractContextTest;
use AsyncInterop\Loop;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends AbstractContextTest {
    public function createContext(callable $function) {
        return new Thread($function);
    }

    public function testSpawnStartsThread() {
        Loop::execute(\Amp\wrap(function () {
            $thread = Thread::spawn(function () {
                usleep(100);
            });

            return yield $thread->join();
        }));

    }
}
