<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Cancellation;
use Amp\Parallel\Sync\Channel;

final class ContextChannel implements Channel
{
    public function __construct(
        private Channel $channel,
    ) {
    }

    public function send(mixed $data): void
    {
        $this->channel->send(new ContextMessage($data));
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->channel->receive($cancellation);
    }
}
