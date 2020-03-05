<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;

abstract class AbstractParcelTest extends AsyncTestCase
{
    /**
     * @param mixed $value
     *
     * @return Promise<Parcel>
     */
    abstract protected function createParcel($value): Promise;

    public function testUnwrapIsOfCorrectType(): \Generator
    {
        $parcel = yield $this->createParcel(new \stdClass);
        \assert($parcel instanceof Parcel);
        $this->assertInstanceOf('stdClass', yield $parcel->unwrap());
    }

    public function testUnwrapIsEqual(): \Generator
    {
        $object = new \stdClass;
        $parcel = yield $this->createParcel($object);
        \assert($parcel instanceof Parcel);
        $this->assertEquals($object, yield $parcel->unwrap());
    }

    public function testSynchronized(): \Generator
    {
        $parcel = yield $this->createParcel(0);
        \assert($parcel instanceof Parcel);

        $value = yield $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            \usleep(10000);
            return 1;
        });

        $this->assertSame(1, $value);

        $value = yield $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            \usleep(10000);
            return 2;
        });

        $this->assertSame(2, $value);
    }
}
