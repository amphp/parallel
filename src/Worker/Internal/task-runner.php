<?php declare(strict_types=1);

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Worker;
use Amp\Sync\Channel;

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

    Worker\runTasks($channel);

    return 0;
};
