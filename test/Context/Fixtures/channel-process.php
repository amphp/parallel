<?php declare(strict_types=1);

use Amp\Sync\Channel;

return function (Channel $channel) use ($argv): string {
    $value = $channel->receive();
    $channel->send($value);
    return $value;
};
