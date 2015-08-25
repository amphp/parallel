<?php
namespace Icicle\Concurrent\Worker;

class HelloTask implements TaskInterface
{
    public function run()
    {
        return "Hello, world!";
    }
}
