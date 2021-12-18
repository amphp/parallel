<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Parallel\Context\Parallel;

/**
 * The built-in worker factory type.
 */
final class DefaultWorkerFactory implements WorkerFactory
{
    /** @var string */
    private string $className;

    /**
     * @param string $cacheClass Name of class implementing {@see Cache} to instigate in each
     *     worker. Defaults to {@see LocalCache}.
     *
     * @throws \Error If the given class name does not exist or does not implement {@see Cache}.
     */
    public function __construct(string $cacheClass = LocalCache::class)
    {
        if (!\class_exists($cacheClass)) {
            throw new \Error(\sprintf("Invalid cache class name '%s'", $cacheClass));
        }

        if (!\is_subclass_of($cacheClass, Cache::class)) {
            throw new \Error(\sprintf(
                "The class '%s' does not implement '%s'",
                $cacheClass,
                Cache::class
            ));
        }

        $this->className = $cacheClass;
    }

    /**
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available a WorkerProcess will be created.
     */
    public function create(): Worker
    {
        if (Parallel::isSupported()) {
            return new WorkerParallel($this->className);
        }

        return new WorkerProcess(
            $this->className,
            [],
            \getenv("AMP_PHP_BINARY") ?: (\defined("AMP_PHP_BINARY") ? \AMP_PHP_BINARY : null)
        );
    }
}
