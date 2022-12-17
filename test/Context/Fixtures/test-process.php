<?php declare(strict_types=1);

use Amp\Sync\Channel;

return function (Channel $channel) use ($argv): string {
    if (!isset($argv[1])) {
        throw new Error("No string provided");
    }

    return $argv[1];
};
