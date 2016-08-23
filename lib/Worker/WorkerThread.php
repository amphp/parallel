<?php declare(strict_types = 1);

namespace Amp\Parallel\Worker;

use Amp\Parallel\Threading\Thread;
use Amp\Parallel\Worker\Internal\TaskRunner;
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
