<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Parcel;

/**
 * @requires extension shmop
 * @requires extension sysvmsg
 */
class ParcelTest extends AbstractParcelTest
{
    private $parcel;

    protected function createParcel($value)
    {
        $this->parcel = new Parcel($value);
        return $this->parcel;
    }

    public function tearDown()
    {
        if ($this->parcel !== null) {
            $this->parcel->free();
        }
    }

    public function testCloneIsNewParcel()
    {
        $original = $this->createParcel(1);

        $clone = clone $original;

        $clone->wrap(2);

        $this->assertSame(1, $original->unwrap());
        $this->assertSame(2, $clone->unwrap());

        $clone->free();
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
}
