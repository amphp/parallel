<?php declare(strict_types = 1);

namespace Amp\Parallel;

class TaskException extends \Exception {
    /** @var string Class name of exception thrown from task. */
    private $name;

    /** @var string Stack trace of the exception thrown from task. */
    private $trace;

    /**
     * Creates a new panic error.
     *
     * @param string $name    The exception class name.
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
     * Returns the class name of the exception thrown from the task.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }
    
    /**
     * Gets the stack trace at the point the exception was thrown in the task.
     *
     * @return string
     */
    public function getWorkerTrace(): string {
        return $this->trace;
    }
}
