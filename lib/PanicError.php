<?php

namespace Amp\Parallel;

class PanicError extends \Error {
    /** @var string Class name of uncaught exception. */
    private $name;
    
    /** @var string Stack trace of the panic. */
    private $trace;

    /**
     * Creates a new panic error.
     *
     * @param string $name    The uncaught exception class.
     * @param string $message The panic message.
     * @param int    $code    The panic code.
     * @param string $trace   The panic stack trace.
     */
    public function __construct(string $name, string $message = '', int $code = 0, string $trace = '') {
        parent::__construct($message, $code);
        $this->name = $name;
        $this->trace = $trace;
    }
    
    /**
     * Returns the class name of the uncaught exception.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Gets the stack trace at the point the panic occurred.
     *
     * @return string
     */
    public function getPanicTrace(): string {
        return $this->trace;
    }
}
