<?php
namespace Icicle\Concurrent\Exception;

class WorkerException extends \Exception implements Exception
{
    /**
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
