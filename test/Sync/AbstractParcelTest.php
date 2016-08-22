<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Sync;

use Amp\Concurrent\Test\TestCase;

abstract class AbstractParcelTest extends TestCase {
    /**
     * @return \Amp\Concurrent\Sync\Parcel
     */
    abstract protected function createParcel($value);

    public function testConstructor() {
        $object = $this->createParcel(new \stdClass());
        $this->assertInternalType('object', $object->unwrap());
    }

    public function testUnwrapIsOfCorrectType() {
        $object = $this->createParcel(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->unwrap());
    }

    public function testUnwrapIsEqual() {
        $object = new \stdClass();
        $shared = $this->createParcel($object);
        $this->assertEquals($object, $shared->unwrap());
    }

    /**
     * @depends testUnwrapIsEqual
     */
    public function testSynchronized() {
        $parcel = $this->createParcel(0);

        $awaitable = $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            usleep(10000);
            return 1;
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null), $this->identicalTo(1));

        $awaitable->when($callback);

        $awaitable = $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            usleep(10000);
            return 2;
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null), $this->identicalTo(2));

        $awaitable->when($callback);
    }

    /**
     * @depends testSynchronized
     */
    public function testCloneIsNewParcel() {
        $original = $this->createParcel(1);

        $clone = clone $original;

        $awaitable = $clone->synchronized(function () {
            return 2;
        });
        \Amp\wait($awaitable);

        $this->assertSame(1, $original->unwrap());
        $this->assertSame(2, $clone->unwrap());
    }
}
