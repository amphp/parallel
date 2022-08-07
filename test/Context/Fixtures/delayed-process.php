<?php

use Amp\Sync\Channel;
use function Amp\delay;

return function (Channel $channel) use ($argv): int {
    $time = (int) ($argv[1] ?? 1);
    delay($time);
    return $time;
};
