<?php
require dirname(__DIR__).'/vendor/autoload.php';

use Icicle\Concurrent\Forking\ForkContext;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

class Test extends ForkContext
{
    /**
     * @synchronized
     */
    public $data;

    public function run()
    {
        print "Child sleeping for 4 seconds...\n";
        sleep(4);

        yield $this->synchronized(function () {
            $this->data = 'progress';
        });

        print "Child sleeping for 2 seconds...\n";
        sleep(2);
    }
}

$generator = function () {
    $context = new Test();
    $context->data = 'blank';
    $context->start();

    Loop\timer(1, function () use ($context) {
        $context->synchronized(function ($context) {
            print "Finally got lock from child!\n";
        });
    });

    $timer = Loop\periodic(1, function () use ($context) {
        static $i;
        $i = $i + 1 ?: 1;
        print "Demonstrating how alive the parent is for the {$i}th time.\n";

        if ($context->isRunning()) {
            $context->synchronized(function ($context) {
                printf("Context data: '%s'\n", $context->data);
            });
        }
    });

    try {
        yield $context->join();
        print "Context done!\n";
    } catch (Exception $e) {
        print "Error from child!\n";
        print $e."\n";
    } finally {
        $timer->stop();
    }
};

new Coroutine($generator());
Loop\run();
