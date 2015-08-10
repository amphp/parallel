<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\SharedObject;
use Icicle\Tests\Concurrent\TestCase;

class SharedObjectTest extends TestCase
{
    public function testConstructor()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertInternalType('object', $object->deref());
        $object->free();
    }

    public function testDerefIsOfCorrectType()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->deref());
        $object->free();
    }

    public function testDerefIsEqual()
    {
        $object = new \stdClass();
        $shared = new SharedObject($object);
        $this->assertEquals($object, $shared->deref());
        $shared->free();
    }

    public function testNewObjectIsNotFreed()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertFalse($object->isFreed());
        $object->free();
    }

    public function testFreeReleasesObject()
    {
        $object = new SharedObject(new \stdClass());
        $object->free();
        $this->assertTrue($object->isFreed());
    }

    public function testSet()
    {
        $shared = new SharedObject(3);
        $this->assertEquals(3, $shared->deref());

        $shared->set(4);
        $this->assertEquals(4, $shared->deref());

        $shared->free();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\SharedMemoryException
     */
    public function testDerefThrowsErrorIfFreed()
    {
        $object = new SharedObject(new \stdClass());
        $object->free();
        $object->deref();
    }

    public function testCloneIsNewObject()
    {
        $object = new \stdClass();
        $shared = new SharedObject($object);
        $clone = clone $shared;

        $this->assertNotSame($shared, $clone);
        $this->assertNotSame($object, $clone->deref());
        $this->assertNotEquals($shared->__debugInfo()['id'], $clone->__debugInfo()['id']);

        $clone->free();
        $shared->free();
    }

    /**
     * @group posix
     */
    public function testSetInSeparateProcess()
    {
        $object = new SharedObject(42);

        $this->doInFork(function () use ($object) {
            $object->set(43);
        });

        $this->assertEquals(43, $object->deref());
        $object->free();
    }

    /**
     * @group posix
     */
    public function testFreeInSeparateProcess()
    {
        $object = new SharedObject(42);

        $this->doInFork(function () use ($object) {
            $object->free();
        });

        $this->assertTrue($object->isFreed());
    }

    private function doInFork(callable $function)
    {
        switch (pcntl_fork()) {
            case -1:
                $this->fail('Failed to fork process.');
                break;
            case 0:
                $status = (int)$function();
                exit(0);
            default:
                pcntl_wait($status);
                return $status;
        }
    }
}
