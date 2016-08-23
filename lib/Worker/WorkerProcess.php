<?php declare(strict_types = 1);

namespace Amp\Parallel\Worker;

use Amp\Parallel\Process\ChannelledProcess;

/**
 * A worker thread that executes task objects.
 */
class WorkerProcess extends AbstractWorker {
    public function __construct() {
        $dir = \dirname(__DIR__, 2) . '/bin';
        parent::__construct(new ChannelledProcess($dir . '/worker', $dir));
    }
}
