<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Thread;
use Amp\Promise;

/**
 * A worker thread that executes task objects.
 */
class WorkerThread extends AbstractWorker {
    /**
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     */
    public function __construct(string $envClassName = BasicEnvironment::class) {
        parent::__construct(new Thread(function (string $className): Promise {
            try {
                $reflection = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new \Error(\sprintf("Invalid class name '%s'", $className));
            }

            if (!$reflection->isInstantiable()) {
                throw new \Error(\sprintf("'%s' is not instatiable class", $className));
            }

            if (!$reflection->implementsInterface(Environment::class)) {
                throw new \Error(\sprintf("The class '%s' does not implement '%s'", $className, Environment::class));
            }

            $environment = $reflection->newInstance();

            if (!\defined("AMP_WORKER")) {
                \define("AMP_WORKER", "amp-worker");
            }

            $runner = new TaskRunner($this, $environment);
            return $runner->run();
        }, $envClassName));
    }
}
