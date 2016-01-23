<?php
namespace Icicle\Examples\Concurrent;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Task;

class BlockingTask implements Task
{
    /**
     * @var callable
     */
    private $function;

    /**
     * @var mixed[]
     */
    private $args;

    /**
     * @param callable $function Do not use a closure or non-serializable object.
     * @param mixed ...$args Arguments to pass to the function. Must be serializable.
     */
    public function __construct(callable $function, ...$args)
    {
        $this->function = $function;
        $this->args = $args;
    }

    /**
     * {@inheritdoc}
     */
    public function run(Environment $environment): \Generator
    {
        return yield ($this->function)(...$this->args);
    }
}
