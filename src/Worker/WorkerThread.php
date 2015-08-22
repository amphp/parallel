<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Threading\ThreadContext;
use Icicle\Coroutine\Coroutine;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread implements WorkerInterface
{
    /**
     * @var ThreadContext A handle to the running thread.
     */
    private $thread;

    /**
     * Creates a new worker thread.
     */
    public function __construct()
    {
        $this->thread = new ThreadContext(function () {
            while (true) {
                print "Waiting for task...\n";
                $task = (yield $this->receive());
                var_dump($task);

                // Shutdown request
                if ($task === 1) {
                    print "Shutting down...\n";
                    break;
                }

                if (!($task instanceof TaskInterface)) {
                    throw new \Exception('Invalid message.');
                }

                $returnValue = $task->run();
                if ($returnValue instanceof \Generator) {
                    $returnValue = (yield new Coroutine($returnValue));
                }

                yield $this->send($returnValue);
            }

            print "End of worker.\n";
            yield null;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->thread->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->thread->start();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->thread->kill();
    }

    /**
     * @return \Generator
     */
    public function join()
    {
        return $this->thread->join();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        yield $this->thread->send(1);
        yield $this->thread->join();
        var_dump('SENT SHUTDOWN');
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(TaskInterface $task)
    {
        yield $this->thread->send($task);

        $returnValue = (yield $this->thread->receive());
        yield $returnValue;
    }
}
