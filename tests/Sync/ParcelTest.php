<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Parcel;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @requires extension shmop
 */
class ParcelTest extends TestCase
{
    public function testConstructor()
    {
        $object = new Parcel(new \stdClass());
        $this->assertInternalType('object', $object->unwrap());
        $object->free();
    }

    public function testUnwrapIsOfCorrectType()
    {
        $object = new Parcel(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->unwrap());
        $object->free();
    }

    public function testUnwrapIsEqual()
    {
        $object = new \stdClass();
        $shared = new Parcel($object);
        $this->assertEquals($object, $shared->unwrap());
        $shared->free();
    }

    public function testNewObjectIsNotFreed()
    {
        $object = new Parcel(new \stdClass());
        $this->assertFalse($object->isFreed());
        $object->free();
    }

    public function testFreeReleasesObject()
    {
        $object = new Parcel(new \stdClass());
        $object->free();
        $this->assertTrue($object->isFreed());
    }

    public function testWrap()
    {
        $shared = new Parcel(3);
        $this->assertEquals(3, $shared->unwrap());

        $shared->wrap(4);
        $this->assertEquals(4, $shared->unwrap());

        $shared->free();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\SharedMemoryException
     */
    public function testUnwrapThrowsErrorIfFreed()
    {
        $object = new Parcel(new \stdClass());
        $object->free();
        $object->unwrap();
    }

    public function testCloneIsNewObject()
    {
        $object = new \stdClass();
        $shared = new Parcel($object);
        $clone = clone $shared;

        $this->assertNotSame($shared, $clone);
        $this->assertNotSame($object, $clone->unwrap());
        $this->assertNotEquals($shared->__debugInfo()['id'], $clone->__debugInfo()['id']);

        $clone->free();
        $shared->free();
    }

    public function testObjectOverflowMoved()
    {
        $object = new Parcel('hi', 14);
        $object->wrap('hello world');

        $this->assertEquals('hello world', $object->unwrap());
        $object->free();
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testSetInSeparateProcess()
    {
        $object = new Parcel(42);

        $this->doInFork(function () use ($object) {
            $object->wrap(43);
        });

        $this->assertEquals(43, $object->unwrap());
        $object->free();
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testFreeInSeparateProcess()
    {
        $object = new Parcel(42);

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
