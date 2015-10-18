<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Parcel;
use Icicle\Coroutine\Coroutine;

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
        $coroutine = new Coroutine($object->synchronized(function () {
            return 'hello world';
        }));
        $coroutine->wait();

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
            $coroutine = new Coroutine($object->synchronized(function () {
                return 43;
            }));
            $coroutine->wait();
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
