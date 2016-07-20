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
        parent::__construct(new Thread(function (): \Generator {
            $runner = new TaskRunner($this, new BasicEnvironment());
            return yield from $runner->run();
        }));
    }
}
