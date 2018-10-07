<?php

namespace Amp\Parallel\Test\Sync;

use Amp\PHPUnit\TestCase;
use Amp\Promise;

abstract class AbstractParcelTest extends TestCase
{
    /**
     * @return \Amp\Parallel\Sync\Parcel
     */
    abstract protected function createParcel($value);

    public function testUnwrapIsOfCorrectType()
    {
        $object = $this->createParcel(new \stdClass);
        $this->assertInstanceOf('stdClass', Promise\wait($object->unwrap()));
    }

    public function testUnwrapIsEqual()
    {
        $object = new \stdClass;
        $shared = $this->createParcel($object);
        $this->assertEquals($object, Promise\wait($shared->unwrap()));
    }

    /**
     * @depends testUnwrapIsEqual
     */
    public function testSynchronized()
    {
        $parcel = $this->createParcel(0);

        $awaitable = $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            \usleep(10000);
            return 1;
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null), $this->identicalTo(1));

        $awaitable->onResolve($callback);

        $awaitable = $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            \usleep(10000);
            return 2;
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null), $this->identicalTo(2));

        $awaitable->onResolve($callback);
    }
}
