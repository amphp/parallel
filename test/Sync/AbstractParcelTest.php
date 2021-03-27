<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\PHPUnit\AsyncTestCase;

abstract class AbstractParcelTest extends AsyncTestCase
{
    public function testUnwrapIsOfCorrectType(): void
    {
        $parcel = $this->createParcel(new \stdClass);
        \assert($parcel instanceof Parcel);
        self::assertInstanceOf('stdClass', $parcel->unwrap());
    }

    public function testUnwrapIsEqual(): void
    {
        $object = new \stdClass;
        $parcel = $this->createParcel($object);
        \assert($parcel instanceof Parcel);
        self::assertEquals($object, $parcel->unwrap());
    }

    public function testSynchronized(): void
    {
        $parcel = $this->createParcel(0);
        \assert($parcel instanceof Parcel);

        $value = $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            \usleep(10000);
            return 1;
        });

        self::assertSame(1, $value);

        $value = $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            \usleep(10000);
            return 2;
        });

        self::assertSame(2, $value);
    }

    abstract protected function createParcel($value): Parcel;
}
