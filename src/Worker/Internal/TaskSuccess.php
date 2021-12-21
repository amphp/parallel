<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Worker\Task;

/**
 * @internal
 *
 * @template T
 * @template-extends TaskResult<T>
 */
final class TaskSuccess extends TaskResult
{
    /** @var T Result of task. */
    private mixed $result;

    /**
     * @param string $id
     * @param T $result
     */
    public function __construct(string $id, mixed $result)
    {
        parent::__construct($id);
        $this->result = $result;
    }

    /**
     * @return T
     */
    public function getResult(): mixed
    {
        if ($this->result instanceof \__PHP_Incomplete_Class) {
            throw new \Error(\sprintf(
                "Class instances returned from %s::run() must be autoloadable by the Composer autoloader",
                Task::class
            ));
        }

        return $this->result;
    }
}
