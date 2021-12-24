<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\async;
use function Amp\delay;

abstract class AbstractParcelTest extends AsyncTestCase
{
    public function testUnwrapIsOfCorrectType(): void
    {
        $parcel = $this->createParcel(new \stdClass);
        self::assertInstanceOf('stdClass', $parcel->unwrap());
    }

    public function testUnwrapIsEqual(): void
    {
        $object = new \stdClass;
        $parcel = $this->createParcel($object);
        self::assertEquals($object, $parcel->unwrap());
    }

    public function testSynchronized(): void
    {
        $parcel = $this->createParcel(0);

        $future1 = async(fn () =>$parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            delay(0.2);
            return 1;
        }));

        $future2 = async(fn () => $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            delay(0.1);
            return 2;
        }));

        self::assertSame(1, $future1->await());
        self::assertSame(2, $future2->await());
    }

    abstract protected function createParcel(mixed $value): Parcel;
}
