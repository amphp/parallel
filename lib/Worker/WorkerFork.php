<?php

namespace Amp\Concurrent\Worker;

use Amp\Concurrent\Forking\Fork;
use Amp\Concurrent\Worker\Internal\TaskRunner;
use Interop\Async\Awaitable;

/**
 * A worker thread that executes task objects.
 */
class WorkerFork extends AbstractWorker {
    public function __construct() {
        parent::__construct(new Fork(function (): Awaitable {
            $runner = new TaskRunner($this, new BasicEnvironment);
            return $runner->run();
        }));
    }
}
