<?php

namespace Amp\Parallel\Worker;

final class TaskError extends \Error
{
    /** @var string Class name of error thrown from task. */
    private $name;

    /** @var string Stack trace of the error thrown from task. */
    private $trace;

    /**
     * @param string $name The exception class name.
     * @param string $message The panic message.
     * @param string $trace The panic stack trace.
     */
    public function __construct(string $name, string $message = '', string $trace = '')
    {
        parent::__construct($message);

        $this->name = $name;
        $this->trace = $trace;
    }

    /**
     * Returns the class name of the error thrown from the task.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the stack trace at the point the error was thrown in the task.
     *
     * @return string
     */
    public function getWorkerTrace(): string
    {
        return $this->trace;
    }
}
