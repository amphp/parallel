<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Threading\Thread;
use Amp\Parallel\Worker\Internal\TaskRunner;
use AsyncInterop\Promise;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends AbstractWorker {
    public function __construct() {
        parent::__construct(new Thread(function (): Promise {
            $runner = new TaskRunner($this, new BasicEnvironment);
            return $runner->run();
        }));
    }
}
