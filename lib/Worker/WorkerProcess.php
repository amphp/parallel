<?php

namespace Amp\Concurrent\Worker;

use Amp\Concurrent\Process\ChannelledProcess;

/**
 * A worker thread that executes task objects.
 */
class WorkerProcess extends AbstractWorker {
    public function __construct() {
        $dir = \dirname(\dirname(__DIR__)) . '/bin';
        parent::__construct(new ChannelledProcess($dir . '/worker.php', $dir));
    }
}
