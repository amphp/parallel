<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Threading\LocalObject;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 */
class LocalObjectTest extends TestCase
{
    public function testConstructor()
    {
        $object = new LocalObject(new \stdClass());
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testNonObjectThrowsError()
    {
        $object = new LocalObject(42);
    }

    public function testDerefIsOfCorrectType()
    {
        $object = new LocalObject(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->deref());
    }

    public function testDerefIsSameObject()
    {
        $object = new \stdClass();
        $local = new LocalObject($object);
        $this->assertSame($object, $local->deref());
    }

    public function testNewObjectIsNotFreed()
    {
        $object = new LocalObject(new \stdClass());
        $this->assertFalse($object->isFreed());
    }

    public function testFreeReleasesObject()
    {
        $object = new LocalObject(new \stdClass());
        $object->free();
        $this->assertTrue($object->isFreed());
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\LocalObjectError
     */
    public function testDerefThrowsErrorIfFreed()
    {
        $object = new LocalObject(new \stdClass());
        $object->free();
        $object->deref();
    }

    public function testSerializeDoesntAffectObject()
    {
        $object = new \stdClass();
        $local = new LocalObject($object);
        $local = unserialize(serialize($local));
        $this->assertSame($object, $local->deref());
    }
}
