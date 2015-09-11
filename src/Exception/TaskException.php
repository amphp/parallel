<?php
namespace Icicle\Concurrent\Exception;

class TaskException extends Exception
{
    /**
     * @var string Stack trace of the panic.
     */
    private $trace;

    /**
     * Creates a new panic error.
     *
     * @param string $message The panic message.
     * @param int    $code    The panic code.
     * @param string $trace   The panic stack trace.
     */
    public function __construct($message = '', $code = 0, $trace = '')
    {
        parent::__construct($message, $code);
        $this->trace = $trace;
    }

    /**
     * Gets the stack trace at the point the panic occurred.
     *
     * @return string
     */
    public function getWorkerTrace()
    {
        return $this->trace;
    }
}
