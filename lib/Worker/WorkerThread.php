<?php declare(strict_types = 1);

namespace Amp\Concurrent\Worker;

use Amp\Concurrent\Threading\Thread;
use Amp\Concurrent\Worker\Internal\TaskRunner;
use Interop\Async\Awaitable;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends AbstractWorker {
    public function __construct() {
        parent::__construct(new Thread(function (): Awaitable {
            $runner = new TaskRunner($this, new BasicEnvironment);
            return $runner->run();
        }));
    }
}
