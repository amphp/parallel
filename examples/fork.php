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
        print "Child sleeping for 5 seconds...\n";
        yield $this->sem->acquire();
        sleep(4);
        yield $this->sem->release();

        $this->synchronized(function () {
            $this->data = 'progress';
        });

        //throw new Exception('Testing exception bubbling.');

        sleep(2);
    }
}

$generator = function () {
    $before = memory_get_usage();
    $context = new Test();
    $after = memory_get_usage();
    $context->data = 'blank';
    printf("Object memory: %d bytes\n", $after - $before);
    $context->start();

    Loop\timer(1, function () use ($context) {
        $context->sem->acquire()->then(function () use ($context) {
            print "Finally got semaphore from child!\n";
            return $context->sem->release();
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
