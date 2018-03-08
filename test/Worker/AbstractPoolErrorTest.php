<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\TestCase;
use Amp\Promise;

abstract class AbstractPoolErrorTest extends TestCase {
    /**
     * @param int $min
     * @param int $max
     *
     * @return \Amp\Parallel\Worker\Pool
     */
    abstract protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool;

    /**
     * @expectedException        \Error
     * @expectedExceptionMessage Maximum size must be a non-negative integer
     */
    public function testCreatePoolShouldThrowError() {
        Loop::run(function () {
            $this->createPool();
        });
    }
}
