<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Threading\ThreadContext;
use Icicle\Loop;

class Test extends ThreadContext
{
    public function run()
    {
        print "Sleeping for 5 seconds...\n";
        sleep(5);
    }
}

$timer = Loop\periodic(1, function () {
    print "Demonstrating how alive the parent is.\n";
});

$test = new Test();
$test->start();
$test->join()->then(function () {
    print "Thread ended!\n";
    Loop\stop();
});

Loop\run();
