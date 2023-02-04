<?php declare(strict_types=1);

namespace Amp\Parallel\Worker\Internal;

use Amp\Cache\AtomicCache;
use Amp\Cache\LocalCache;
use Amp\Parallel\Worker;
use Amp\Sync\Channel;
use Amp\Sync\LocalKeyedMutex;

return static function (Channel $channel) use ($argc, $argv): int {
    if (!\defined("AMP_WORKER")) {
        \define("AMP_WORKER", \AMP_CONTEXT);
    }

    if (isset($argv[1])) {
        if (!\is_file($argv[1])) {
            throw new \Error(\sprintf("No file found at bootstrap path given '%s'", $argv[1]));
        }

        // Include file within closure to protect scope.
        (function () use ($argc, $argv): void {
            /** @psalm-suppress UnresolvableInclude */
            require $argv[1];
        })();
    }

    $cache = new AtomicCache(new LocalCache(), new LocalKeyedMutex());

    Worker\runTasks($channel, $cache);

    return 0;
};
