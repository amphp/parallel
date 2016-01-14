<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Threading\Thread;
use Icicle\Concurrent\Worker\Internal\TaskRunner;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends AbstractWorker
{
    public function __construct()
    {
        parent::__construct(new Thread(function () {
            $runner = new TaskRunner($this, new BasicEnvironment());
            yield $runner->run();
        }));
    }
}
