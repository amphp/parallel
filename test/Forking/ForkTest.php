<?php

namespace Amp\Parallel\Test\Forking;

use Amp\Parallel\Forking\Fork;
use Amp\Parallel\Test\AbstractContextTest;

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkTest extends AbstractContextTest {
    public function createContext(callable $function) {
        return new Fork($function);
    }

    public function testSpawnStartsFork() {
        \Amp\execute(function () {
            $fork = Fork::spawn(function () {
                usleep(100);
            });

            return yield $fork->join();
        });
    }
}
