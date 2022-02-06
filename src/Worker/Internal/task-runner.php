<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Cache\Cache;
use Amp\Parallel\Worker;
use Amp\Sync\Channel;

return static function (Channel $channel) use ($argc, $argv): int {
    if (!\defined("AMP_WORKER")) {
        \define("AMP_WORKER", \AMP_CONTEXT);
    }

    if (isset($argv[2])) {
        if (!\is_file($argv[2])) {
            throw new \Error(\sprintf("No file found at bootstrap path given '%s'", $argv[2]));
        }

        // Include file within closure to protect scope.
        (function () use ($argc, $argv): void {
            /** @psalm-suppress UnresolvableInclude */
            require $argv[2];
        })();
    }

    if (!isset($argv[1])) {
        throw new \Error("No cache class name provided");
    }

    $className = $argv[1];

    if (!\class_exists($className)) {
        throw new \Error(\sprintf("Invalid cache class name '%s'", $className));
    }

    if (!\is_subclass_of($className, Cache::class)) {
        throw new \Error(\sprintf(
            "The class '%s' does not implement '%s'",
            $className,
            Cache::class
        ));
    }

    $cache = new $className;

    $runner = new Worker\TaskRunner($channel, $cache);
    $runner->run();

    return 0;
};
