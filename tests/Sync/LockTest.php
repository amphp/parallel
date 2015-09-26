<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Lock;
use Icicle\Tests\Concurrent\TestCase;

class LockTest extends TestCase
{
    public function testIsReleased()
    {
        $lock = new Lock($this->createCallback(1));
        $this->assertFalse($lock->isReleased());
        $lock->release();
        $this->assertTrue($lock->isReleased());
    }

    public function testIsReleasedOnDestruct()
    {
        $lock = new Lock($this->createCallback(1));
        unset($lock);
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\LockAlreadyReleasedError
     */
    public function testThrowsOnMultiRelease()
    {
        $lock = new Lock($this->createCallback(1));
        $lock->release();
        $lock->release();
    }
}
