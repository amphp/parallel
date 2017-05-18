<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Lock;
use Amp\PHPUnit\TestCase;

class LockTest extends TestCase {
    public function testIsReleased() {
        $lock = new Lock($this->createCallback(1));
        $this->assertFalse($lock->isReleased());
        $lock->release();
        $this->assertTrue($lock->isReleased());
    }

    public function testIsReleasedOnDestruct() {
        $lock = new Lock($this->createCallback(1));
        unset($lock);
    }

    /**
     * @expectedException \Amp\Parallel\Sync\LockAlreadyReleasedError
     */
    public function testThrowsOnMultiRelease() {
        $lock = new Lock($this->createCallback(1));
        $lock->release();
        $lock->release();
    }
}
