<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\ProcessContext;

/**
 * A worker process that executes task objects.
 */
final class WorkerProcess
{
    private const SCRIPT_PATH = __DIR__ . "/Internal/task-runner.php";

    /**
     * @param string $cacheClass Name of class implementing {@see Cache} to instigate. Defaults to {@see LocalCache}.
     * @param string[] $environment Array of environment variables to pass to the worker. Empty array inherits from the
     *     current PHP process. See the $env parameter of \Amp\Process\Process::__construct().
     * @param string|null $binaryPath Path to PHP binary. Null will attempt to automatically locate the binary.
     * @param string|null $bootstrapPath Path to custom bootstrap file.
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     * @throws ContextException
     */
    public static function start(
        string $cacheClass = LocalCache::class,
        array $environment = [],
        ?string $binaryPath = null,
        ?string $bootstrapPath = null
    ): Worker {
        $script = [
            self::SCRIPT_PATH,
            $cacheClass,
        ];

        if ($bootstrapPath !== null) {
            $script[] = $bootstrapPath;
        }

        return new TaskWorker(ProcessContext::start($script, environment: $environment, binaryPath: $binaryPath));
    }
}
