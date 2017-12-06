<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Process;

/**
 * A worker thread that executes task objects.
 */
class WorkerProcess extends AbstractWorker {
    /**
     * @param string|null $binary Path to PHP binary. Null will attempt to automatically locate the binary.
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     * @param mixed[] $env Array of environment variables to pass to the worker. Empty array inherits from the current
     *     PHP process. See the $env parameter of \Amp\Process\Process::__construct().
     */
    public function __construct(string $binary = null, string $envClassName = BasicEnvironment::class, array $env = []) {
        $dir = \dirname(__DIR__, 2) . '/bin';
        $script = [
            $dir . "/worker",
            "-e" . $envClassName,
        ];
        parent::__construct(new Process($script, $binary, $dir, $env));
    }
}
