<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Promise;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends AbstractWorker
{
    /**
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     */
    public function __construct(string $envClassName = BasicEnvironment::class)
    {
        parent::__construct(new Thread(function (Channel $channel, string $className): Promise {
            if (!\class_exists($className)) {
                throw new \Error(\sprintf("Invalid environment class name '%s'", $className));
            }

            if (!\is_subclass_of($className, Environment::class)) {
                throw new \Error(\sprintf("The class '%s' does not implement '%s'", $className, Environment::class));
            }

            $environment = new $className;

            if (!\defined("AMP_WORKER")) {
                \define("AMP_WORKER", "amp-worker");
            }

            $runner = new TaskRunner($channel, $environment);
            return $runner->run();
        }, $envClassName));
    }
}
