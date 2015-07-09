<?php
namespace Icicle\Concurrent;

/**
 * Interface for all types of execution contexts.
 */
interface Context
{
    /**
     * Executes a task inside the context.
     *
     * @param Task $task [description]
     *
     * @return [type] [description]
     */
    public function run(Task $task);
}
