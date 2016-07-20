<?php
namespace Icicle\Concurrent\Exception;

class ChannelException extends \Exception implements Exception
{
    public function __construct(string $message, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
