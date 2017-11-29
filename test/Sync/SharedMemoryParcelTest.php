<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Loop;
use Amp\Parallel\Sync\SharedMemoryParcel;
use Amp\Promise;

/**
 * @requires extension shmop
 * @requires extension sysvmsg
 */
class SharedMemoryParcelTest extends AbstractParcelTest {
    const ID = __CLASS__;

    private $parcel;

    protected function createParcel($value) {
        $this->parcel = SharedMemoryParcel::create(self::ID, $value);
        return $this->parcel;
    }

    public function tearDown() {
        $this->parcel = null;
    }

    public function testObjectOverflowMoved() {
        $object = SharedMemoryParcel::create(self::ID, 'hi', 2);
        $awaitable = $object->synchronized(function () {
            return 'hello world';
        });
        Promise\wait($awaitable);

        $this->assertEquals('hello world', Promise\wait($object->unwrap()));
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testSetInSeparateProcess() {
        $object = SharedMemoryParcel::create(self::ID, 42);

        $this->doInFork(function () use ($object) {
            $awaitable = $object->synchronized(function ($value) {
                return $value + 1;
            });
            Promise\wait($awaitable);
        });

        $this->assertEquals(43, Promise\wait($object->unwrap()));
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testInSeparateProcess() {
        $parcel = SharedMemoryParcel::create(self::ID, 42);

        $this->doInFork(function () {
            Loop::run(function () {
                $parcel = SharedMemoryParcel::use(self::ID);
                $this->assertSame(43, yield $parcel->synchronized(function ($value) {
                    $this->assertSame(42, $value);
                    return $value + 1;
                }));
            });
        });

        Loop::run(function () use ($parcel) {
            $this->assertSame(43, yield $parcel->unwrap());
        });
    }
}
