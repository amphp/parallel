<?php
namespace Icicle\Concurrent\Exception;

class WorkerException extends \Exception implements Exception
{
    /**
     * @param string $message
     * @param \Exception|null $previous
     */
    public function __construct($message, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
