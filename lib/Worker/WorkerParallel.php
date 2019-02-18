<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Parallel;

/**
 * A worker parallel extension thread that executes task objects.
 */
final class WorkerParallel extends TaskWorker
{
    const SCRIPT_PATH = __DIR__ . "/Internal/worker-process.php";

    /**
     * @param string $envClassName Name of class implementing \Amp\Parallel\Worker\Environment to instigate.
     *     Defaults to \Amp\Parallel\Worker\BasicEnvironment.
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     */
    public function __construct(string $envClassName = BasicEnvironment::class)
    {
        parent::__construct(new Parallel([
            self::SCRIPT_PATH,
            $envClassName,
        ]));
    }
}
