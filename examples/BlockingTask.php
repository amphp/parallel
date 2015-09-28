<?php
namespace Icicle\Examples\Concurrent;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\TaskInterface;

class BlockingTask implements TaskInterface
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
    public function __construct(callable $function /* ...$args */)
    {
        $this->function = $function;
        $this->args = array_slice(func_get_args(), 1);
    }

    /**
     * {@inheritdoc}
     */
    public function run(Environment $environment)
    {
        return call_user_func_array($this->function, $this->args);
    }
}
