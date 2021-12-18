<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\LocalCache;
use Amp\Parallel\Context\ParallelContext;

/**
 * A worker parallel extension thread that executes task objects.
 */
final class WorkerParallel extends TaskWorker
{
    private const SCRIPT_PATH = __DIR__ . "/Internal/worker-process.php";

    /**
     * @param string $cacheClass Name of class implementing {@see Cache} to instigate. Defaults to {@see LocalCache}.
     * @param string|null Path to custom bootstrap file.
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     */
    public function __construct(string $cacheClass = LocalCache::class, ?string $bootstrapPath = null)
    {
        $script = [
            self::SCRIPT_PATH,
            $cacheClass,
        ];

        if ($bootstrapPath !== null) {
            $script[] = $bootstrapPath;
        }

        parent::__construct(ParallelContext::start($script));
    }
}
