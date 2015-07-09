<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Forking\ForkContext;

class Test extends ForkContext
{
    public function run()
    {
        print "Exiting in 5 seconds...\n";
        sleep(5);
        print "Context exiting...\n";
    }
}

$context = new Test();
$context->start()->then(function () {
    print "Context finished!\n";
    Icicle\Loop\stop();
});

print "Context started.\n";

Icicle\Loop\periodic(1, function () {
    static $i;
    $i = $i + 1 ?: 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

Icicle\Loop\run();
