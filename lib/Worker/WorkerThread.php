<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Promise;

/**
 * A worker thread that executes task objects.
 */
final class WorkerThread extends TaskWorker
{
    /**
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     * @param string|null Path to custom autoloader.
     */
    public function __construct(string $envClassName = BasicEnvironment::class, string $autoloadPath = null)
    {
        parent::__construct(new Thread(function (Channel $channel, string $className, string $autoloadPath = null): Promise {
            if ($autoloadPath !== null) {
                if (!\is_file($autoloadPath)) {
                    throw new \Error(\sprintf("No file found at autoload path given '%s'", $autoloadPath));
                }

                // Include file within unbound closure to protect scope.
                (function () use ($autoloadPath) {
                    require $autoloadPath;
                })->bindTo(null, null)();
            }

            if (!\class_exists($className)) {
                throw new \Error(\sprintf("Invalid environment class name '%s'", $className));
            }

            if (!\is_subclass_of($className, Environment::class)) {
                throw new \Error(\sprintf("The class '%s' does not implement '%s'", $className, Environment::class));
            }

            $environment = new $className;

            if (!\defined("AMP_WORKER")) {
                \define("AMP_WORKER", \AMP_CONTEXT);
            }

            $runner = new TaskRunner($channel, $environment);
            return $runner->run();
        }, $envClassName, $autoloadPath));
    }
}
