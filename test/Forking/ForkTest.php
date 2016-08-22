<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Forking;

use Amp\Concurrent\Forking\Fork;
use Amp\Concurrent\Test\AbstractContextTest;

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
