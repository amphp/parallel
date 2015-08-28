<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Parcel;
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

    public function testWrap()
    {
        $shared = $this->createParcel(3);
        $this->assertEquals(3, $shared->unwrap());

        $shared->wrap(4);
        $this->assertEquals(4, $shared->unwrap());
    }
}
