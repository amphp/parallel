<?php

use Amp\Sync\Channel;

return function (Channel $channel) use ($argv): string {
    if (!isset($argv[1])) {
        throw new Error("No string provided");
    }

    return $argv[1];
};
