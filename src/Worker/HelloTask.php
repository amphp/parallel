<?php
namespace Icicle\Concurrent\Worker;

class HelloTask implements TaskInterface
{
    public function run(Environment $environment)
    {
        return "Hello, world!";
    }
}
