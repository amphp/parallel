<?php
namespace Icicle\Benchmarks\Concurrent;

use Athletic\AthleticEvent;

/**
 * Profiles reading and writing serialized variables to direct shared memory
 * using the shmop extension.
 *
 * Note that this is meant to compare with ThreadedMemoryEvent, as reading and
 * writing using serialized memory is nearly the same regardless of the data
 * type.
 */
class SharedMemoryEvent extends AthleticEvent
{
    private $shm;

    public function classSetUp()
    {
        $this->shm = shmop_open(ftok(__FILE__, 't'), 'c', 0666, 86400);
        $this->write((object) [
            'bool' => false,
            'int' => 1,
            'string' => 'hello',
            'object' => new \stdClass(),
        ]);
    }

    public function classTearDown()
    {
        shmop_delete($this->shm);
        shmop_close($this->shm);
    }

    private function read()
    {
        return unserialize(shmop_read($this->shm, 0, shmop_size($this->shm)));
    }

    private function write($object)
    {
        shmop_write($this->shm, serialize($object), 0);
    }

    /**
     * @iterations 10000
     */
    public function readBool()
    {
        $bool = $this->read()->bool;
    }

    /**
     * @iterations 10000
     */
    public function writeBool()
    {
        $object = $this->read();
        $object->bool = true;
        $this->write($object);
    }

    /**
     * @iterations 10000
     */
    public function readInt()
    {
        $int = $this->read()->int;
    }

    /**
     * @iterations 10000
     */
    public function writeInt()
    {
        $object = $this->read();
        $object->int = 2;
        $this->write($object);
    }

    /**
     * @iterations 10000
     */
    public function readString()
    {
        $string = $this->read()->string;
    }

    /**
     * @iterations 10000
     */
    public function writeString()
    {
        $object = $this->read();
        $object->string = 'world';
        $this->write($object);
    }

    /**
     * @iterations 10000
     */
    public function readObject()
    {
        $object = $this->read()->object;
    }

    /**
     * @iterations 10000
     */
    public function writeObject()
    {
        $object = $this->read();
        $object->object = new \stdClass();
        $this->write($object);
    }
}
