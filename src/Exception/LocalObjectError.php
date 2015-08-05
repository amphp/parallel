<?php
namespace Icicle\Concurrent\Exception;

class LocalObjectError extends Error
{
    private $objectId;
    private $threadId;

    public function __construct($message, $objectId, $threadId, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getObjectId()
    {
        return $this->objectId;
    }

    public function getThreadId()
    {
        return $this->threadId;
    }
}
