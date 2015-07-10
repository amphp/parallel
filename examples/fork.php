<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Loop;
use Icicle\Concurrent\Forking\ForkContext;

class Test extends ForkContext
{
    public function run()
    {
        print "Child sleeping for 5 seconds...\n";
        sleep(3);

        $this->synchronized(function () {
            $this->data = 'progress';
        });

        sleep(2);
    }
}

$context = new Test();
$context->data = 'blank';
$context->start();
$context->join()->then(function () {
    print "Context done!\n";
    Loop\stop();
});

Loop\periodic(1, function () use ($context) {
    static $i;
    $i = $i + 1 ?: 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";

    $context->synchronized(function ($context) {
        printf("Context data: '%s'\n", $context->data);
    });
});

Loop\run();
