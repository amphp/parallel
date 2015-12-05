<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

abstract class AbstractParcelTest extends TestCase
{
    /**
     * @return \Icicle\Concurrent\Sync\Parcel
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
    public function testSynchronized()
    {
        $parcel = $this->createParcel(0);

        $coroutine = new Coroutine($parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            usleep(1e4);
            return 1;
        }));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(1));

        $coroutine->done($callback);

        $coroutine = new Coroutine($parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            usleep(1e4);
            return 2;
        }));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(2));

        $coroutine->done($callback);

        Loop\run();
    }

    /**
     * @depends testSynchronized
     */
    public function testCloneIsNewParcel()
    {
        $original = $this->createParcel(1);

        $clone = clone $original;

        $coroutine = new Coroutine($clone->synchronized(function () {
            return 2;
        }));
        $coroutine->wait();

        $this->assertSame(1, $original->unwrap());
        $this->assertSame(2, $clone->unwrap());
    }
}
