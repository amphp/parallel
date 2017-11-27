<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Loop;
use Amp\PHPUnit\TestCase;

abstract class AbstractParcelTest extends TestCase {
    /**
     * @return \Amp\Parallel\Sync\Parcel
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
        $object = new \stdClass;
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

        $awaitable->onResolve($callback);

        $awaitable = $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            usleep(10000);
            return 2;
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null), $this->identicalTo(2));

        $awaitable->onResolve($callback);
    }

    /**
     * @depends testSynchronized
     */
    public function testCloneIsNewParcel() {
        Loop::run(function () {
            $original = $this->createParcel(1);

            $clone = clone $original;

            $this->assertSame(2, yield $clone->synchronized(function () {
                return 2;
            }));

            $this->assertSame(1, yield $original->unwrap());
            $this->assertSame(2, $clone->unwrap());
        });
    }
}
