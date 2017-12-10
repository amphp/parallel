<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Process;

/**
 * A worker thread that executes task objects.
 */
class WorkerProcess extends AbstractWorker {
    const SCRIPT_PATH = __DIR__ . "/Internal/worker-process.php";

    /**
     * @param string|null $binary Path to PHP binary. Null will attempt to automatically locate the binary.
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     * @param mixed[] $env Array of environment variables to pass to the worker. Empty array inherits from the current
     *     PHP process. See the $env parameter of \Amp\Process\Process::__construct().
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     */
    public function __construct(string $binary = null, string $envClassName = BasicEnvironment::class, array $env = []) {
        $script = [
            self::SCRIPT_PATH,
            $envClassName,
        ];
        parent::__construct(new Process($script, $binary, __DIR__, $env));
    }
}
