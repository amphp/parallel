<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

/** @internal */
class Job {
    /** @var string */
    private $id;

    /** @var \Amp\Parallel\Worker\Task */
    private $task;

    public function __construct(Task $task) {
        $this->task = $task;
        $this->id = \spl_object_hash($this->task);
    }

    public function getId(): string {
        return $this->id;
    }

    public function getTask(): Task {
        // Classes that cannot be autoloaded will be unserialized as an instance of __PHP_Incomplete_Class.
        if ($this->task instanceof \__PHP_Incomplete_Class) {
            return new class implements Task {
                public function run(Environment $environment) {
                    throw new \Error(\sprintf("Classes implementing %s must be autoloadable by the Composer autoloader", Task::class));
                }
            };
        }

        return $this->task;
    }
}
