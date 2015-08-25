<?php
namespace Icicle\Concurrent\Worker;

class HelloTask implements TaskInterface
{
    public function run()
    {
        echo "Hello";
        return 42;
    }
}
