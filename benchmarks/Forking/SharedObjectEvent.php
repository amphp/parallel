<?php
namespace Icicle\Benchmarks\Concurrent\Forking;

use Athletic\AthleticEvent;
use Icicle\Concurrent\Forking\SharedObject;

/**
 * Profiles reading and writing variables to shared memory using the SharedObject
 * class.
 */
class SharedObjectEvent extends AthleticEvent
{
    private $sharedObject;

    public function classSetUp()
    {
        $this->sharedObject = new SharedObjectModel();
    }

    public function classTearDown()
    {
        $this->sharedObject->destroy();
    }

    /**
     * @iterations 10000
     */
    public function construction()
    {
        $this->sharedObject = new SharedObjectModel();
    }

    /**
     * @iterations 10000
     */
    public function readBool()
    {
        $bool = $this->sharedObject->bool;
    }

    /**
     * @iterations 10000
     */
    public function writeBool()
    {
        $this->sharedObject->bool = true;
    }

    /**
     * @iterations 10000
     */
    public function readInt()
    {
        $int = $this->sharedObject->int;
    }

    /**
     * @iterations 10000
     */
    public function writeInt()
    {
        $this->sharedObject->int = 2;
    }

    /**
     * @iterations 10000
     */
    public function readString()
    {
        $string = $this->sharedObject->string;
    }

    /**
     * @iterations 10000
     */
    public function writeString()
    {
        $this->sharedObject->string = 'world';
    }

    /**
     * @iterations 10000
     */
    public function readObject()
    {
        $object = $this->sharedObject->object;
    }

    /**
     * @iterations 10000
     */
    public function writeObject()
    {
        $this->sharedObject->object = new \stdClass();
    }
}

class SharedObjectModel extends SharedObject
{
    /**
     * @synchronized
     */
    public $bool = false;

    /**
     * @synchronized
     */
    public $int = 1;

    /**
     * @synchronized
     */
    public $string = 'hello';

    /**
     * @synchronized
     */
    public $object;

    public function __construct()
    {
        $this->object = new \stdClass();
        parent::__construct();
    }
}
