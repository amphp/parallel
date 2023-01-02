<?php declare(strict_types=1);

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Cancellation;
use Amp\Parallel\Context\ContextFactory;
use function Amp\Parallel\Context\contextFactory;

/**
 * The built-in worker factory type.
 */
final class DefaultWorkerFactory implements WorkerFactory
{
    public const SCRIPT_PATH = __DIR__ . "/Internal/task-runner.php";

    /**
     * @param string $cacheClass Name of class implementing {@see Cache} to instantiate in each worker. Defaults
     * to {@see LocalCache}.
     *
     * @throws \Error If the given class name does not exist or does not implement {@see Cache}.
     */
    public function __construct(
        private readonly ?string $bootstrapPath = null,
        private readonly ?ContextFactory $contextFactory = null,
        private readonly string $cacheClass = LocalCache::class,
    ) {
        if (!\class_exists($this->cacheClass)) {
            throw new \Error(\sprintf("Invalid cache class name '%s'", $this->cacheClass));
        }

        if (!\is_subclass_of($this->cacheClass, Cache::class)) {
            throw new \Error(\sprintf(
                "The class '%s' does not implement '%s'",
                $this->cacheClass,
                Cache::class
            ));
        }

        if ($this->bootstrapPath !== null && !\file_exists($this->bootstrapPath)) {
            throw new \Error(\sprintf("No file found at bootstrap path given '%s'", $this->bootstrapPath));
        }
    }

    /**
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available a WorkerProcess will be created.
     */
    public function create(?Cancellation $cancellation = null): Worker
    {
        $script = [
            self::SCRIPT_PATH,
            $this->cacheClass,
        ];

        if ($this->bootstrapPath !== null) {
            $script[] = $this->bootstrapPath;
        }

        return new DefaultWorker(($this->contextFactory ?? contextFactory())->start($script, $cancellation));
    }
}
