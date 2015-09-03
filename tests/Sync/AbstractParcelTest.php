<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\ParcelInterface;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

abstract class AbstractParcelTest extends TestCase
{
    /**
     * @return \Icicle\Concurrent\Sync\ParcelInterface
     */
    abstract protected function createParcel($value);

    public function testConstructor()
    {
        $object = $this->createParcel(new \stdClass());
        $this->assertInternalType('object', $object->unwrap());
    }

    public function testUnwrapIsOfCorrectType()
    {
        $object = $this->createParcel(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->unwrap());
    }

    public function testUnwrapIsEqual()
    {
        $object = new \stdClass();
        $shared = $this->createParcel($object);
        $this->assertEquals($object, $shared->unwrap());
    }

    /**
     * @depends testUnwrapIsEqual
     */
    public function testWrap()
    {
        $shared = $this->createParcel(3);
        $this->assertEquals(3, $shared->unwrap());

        $shared->wrap(4);
        $this->assertEquals(4, $shared->unwrap());
    }

    /**
     * @depends testWrap
     */
    public function testSynchronized()
    {
        $parcel = $this->createParcel(0);

        $coroutine = new Coroutine($parcel->synchronized(function (ParcelInterface $parcel) {
            $this->assertSame(0, $parcel->unwrap());
            usleep(1e4);
            $parcel->wrap(1);
            return -1;
        }));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(-1));

        $coroutine->done($callback);

        $coroutine = new Coroutine($parcel->synchronized(function (ParcelInterface $parcel) {
            $this->assertSame(1, $parcel->unwrap());
            usleep(1e4);
            $parcel->wrap(2);
            return -2;
        }));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(-2));

        $coroutine->done($callback);

        Loop\run();
    }

    /**
     * @depends testWrap
     */
    public function testClone()
    {
        $original = $this->createParcel(1);

        $clone = clone $original;

        $clone->wrap(2);

        $this->assertSame(1, $original->unwrap());
        $this->assertSame(2, $clone->unwrap());
    }
}
