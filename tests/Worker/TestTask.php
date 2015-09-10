<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\TaskInterface;

class TestTask implements TaskInterface
{
    private $returnValue;

    public function __construct($returnValue)
    {
        $this->returnValue = $returnValue;
    }

    public function run(Environment $environment)
    {
        return $this->returnValue;
    }
}
