<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\{Environment, Task};

class TestTask implements Task
{
    private $returnValue;

    public function __construct($returnValue)
    {
        $this->returnValue = $returnValue;
    }

    public function run(Environment $environment): \Generator
    {
        return yield $this->returnValue;
    }
}
