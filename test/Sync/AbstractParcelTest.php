<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;

abstract class AbstractParcelTest extends AsyncTestCase
{
    abstract protected function createParcel($value): Parcel;

    public function testUnwrapIsOfCorrectType()
    {
        $parcel = $this->createParcel(new \stdClass);
        \assert($parcel instanceof Parcel);
        $this->assertInstanceOf('stdClass', $parcel->unwrap());
    }

    public function testUnwrapIsEqual()
    {
        $object = new \stdClass;
        $parcel = $this->createParcel($object);
        \assert($parcel instanceof Parcel);
        $this->assertEquals($object, $parcel->unwrap());
    }

    public function testSynchronized()
    {
        $parcel = $this->createParcel(0);
        \assert($parcel instanceof Parcel);

        $value = $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            \usleep(10000);
            return 1;
        });

        $this->assertSame(1, $value);

        $value = $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            \usleep(10000);
            return 2;
        });

        $this->assertSame(2, $value);
    }
}
