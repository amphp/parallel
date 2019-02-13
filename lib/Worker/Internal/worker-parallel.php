<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Sync;
use Amp\Parallel\Worker;
use Amp\Promise;

return function (Sync\Channel $channel, string $className): Promise {
    if (!\defined("AMP_WORKER")) {
        \define("AMP_WORKER", "parallel");
    }

    if (!\class_exists($className)) {
        throw new \Error(\sprintf("Invalid environment class name '%s'", $className));
    }

    if (!\is_subclass_of($className, Worker\Environment::class)) {
        throw new \Error(\sprintf(
            "The class '%s' does not implement '%s'",
            $className,
            Worker\Environment::class
        ));
    }

    $environment = new $className;

    $runner = new Worker\TaskRunner($channel, $environment);

    return $runner->run();
};
