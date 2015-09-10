<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Threading\Thread;
use Icicle\Concurrent\Worker\Internal\TaskRunner;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends Worker
{
    public function __construct()
    {
        parent::__construct(new Thread(function () {
            $runner = new TaskRunner($this, new Environment());
            yield $runner->run();
        }));
    }
}
