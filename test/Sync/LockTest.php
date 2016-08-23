<?php declare(strict_types = 1);

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Lock;
use Amp\Parallel\Test\TestCase;

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
     * @expectedException \Amp\Parallel\LockAlreadyReleasedError
     */
    public function testThrowsOnMultiRelease() {
        $lock = new Lock($this->createCallback(1));
        $lock->release();
        $lock->release();
    }
}
