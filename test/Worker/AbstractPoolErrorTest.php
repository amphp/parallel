<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\Pool;
use Amp\PHPUnit\TestCase;

abstract class AbstractPoolErrorTest extends TestCase {
    /**
     * @param int $min
     * @param int $max
     *
     * @return \Amp\Parallel\Worker\Pool
     */
    abstract protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool;

    public function testCreatePoolShouldThrowError() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Maximum size must be a non-negative integer");
        Loop::run(function () {
            $this->createPool();
        });
    }
}
