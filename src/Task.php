<?php
namespace Icicle\Concurrent;

/**
 * A synchronous worker object that executes tasks.
 */
class Task
{
    private $task;

    public function __construct(callable $task)
    {
        $this->task = $task;
    }

    /**
     * Runs the task inside the caller's context.
     *
     * @return [type] [description]
     */
    public function runHere()
    {
        call_user_func($this->task);
    }
}
