<?php

namespace Amp\Parallel\Test\Forking;

use Amp\Parallel\Forking\Fork;
use Amp\Parallel\Test\AbstractContextTest;
use Interop\Async\Loop;

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkTest extends AbstractContextTest {
    public function createContext(callable $function) {
        return new Fork($function);
    }

    public function testSpawnStartsFork() {
        Loop::execute(\Amp\wrap(function () {
            $fork = Fork::spawn(function () {
                usleep(100);
            });

            return yield $fork->join();
        }));
    }
}
