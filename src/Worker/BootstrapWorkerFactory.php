<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Parallel\Context\ParallelContext;

/**
 * Worker factory that includes a custom bootstrap file after initializing the worker.
 */
final class BootstrapWorkerFactory implements WorkerFactory
{
    /** @var string */
    private string $bootstrapPath;

    /** @var string */
    private string $className;

    /**
     * @param string $bootstrapFilePath Path to custom bootstrap file.
     * @param string $cacheClass Name of class implementing {@see Cache} to instigate in each
     *     worker. Defaults to {@see LocalCache}.
     *
     * @throws \Error If the given class name does not exist or does not implement {@see Cache}.
     */
    public function __construct(string $bootstrapFilePath, string $cacheClass = LocalCache::class)
    {
        if (!\file_exists($bootstrapFilePath)) {
            throw new \Error(\sprintf("No file found at autoload path given '%s'", $bootstrapFilePath));
        }

        if (!\class_exists($cacheClass)) {
            throw new \Error(\sprintf("Invalid environment class name '%s'", $cacheClass));
        }

        if (!\is_subclass_of($cacheClass, Cache::class)) {
            throw new \Error(\sprintf(
                "The class '%s' does not implement '%s'",
                $cacheClass,
                Cache::class
            ));
        }

        $this->bootstrapPath = $bootstrapFilePath;
        $this->className = $cacheClass;
    }

    /**
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available a WorkerProcess will be created.
     */
    public function create(): Worker
    {
        if (ParallelContext::isSupported()) {
            return WorkerParallel::start($this->className, $this->bootstrapPath);
        }

        return WorkerProcess::start(
            $this->className,
            binaryPath: \getenv("AMP_PHP_BINARY") ?: (\defined("AMP_PHP_BINARY") ? \AMP_PHP_BINARY : null),
            bootstrapPath: $this->bootstrapPath
        );
    }
}
