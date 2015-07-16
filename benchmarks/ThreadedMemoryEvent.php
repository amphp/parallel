<?php
namespace Icicle\Benchmarks\Concurrent;

use Athletic\AthleticEvent;

/**
 * Profiles reading and writing variables to shared threaded memory using the
 * pthreads memory implementation.
 */
class ThreadedMemoryEvent extends AthleticEvent
{
    private $threadedObject;

    public function classSetUp()
    {
        $this->threadedObject = new \Threaded();
        $this->threadedObject->bool = false;
        $this->threadedObject->int = 1;
        $this->threadedObject->string = 'hello';
        $this->threadedObject->object = new \stdClass();
    }

    /**
     * @iterations 10000
     */
    public function readBool()
    {
        $bool = $this->threadedObject->bool;
    }

    /**
     * @iterations 10000
     */
    public function writeBool()
    {
        $this->threadedObject->bool = true;
    }

    /**
     * @iterations 10000
     */
    public function readInt()
    {
        $int = $this->threadedObject->int;
    }

    /**
     * @iterations 10000
     */
    public function writeInt()
    {
        $this->threadedObject->int = 2;
    }

    /**
     * @iterations 10000
     */
    public function readString()
    {
        $string = $this->threadedObject->string;
    }

    /**
     * @iterations 10000
     */
    public function writeString()
    {
        $this->threadedObject->string = 'world';
    }

    /**
     * @iterations 10000
     */
    public function readObject()
    {
        $object = $this->threadedObject->object;
    }

    /**
     * @iterations 10000
     */
    public function writeObject()
    {
        $this->threadedObject->object = new \stdClass();
    }

    /**
     * @iterations 10000
     */
    public function writeThreadedObject()
    {
        $this->threadedObject->object = new \Threaded();
    }
}
