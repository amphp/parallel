<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Forking\Fork;
use Amp\Parallel\Worker\Internal\TaskRunner;
use Interop\Async\Promise;

/**
 * A worker thread that executes task objects.
 */
class WorkerFork extends AbstractWorker {
    public function __construct() {
        parent::__construct(new Fork(function (): Promise {
            $runner = new TaskRunner($this, new BasicEnvironment);
            return $runner->run();
        }));
    }
}
