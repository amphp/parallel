<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Cancellation;
use Amp\Parallel\Sync\Channel;
use Amp\Pipeline\Pipeline;

/** @internal */
final class JobChannel implements Channel
{
    public function __construct(
        private string $id,
        private Channel $channel,
        private Pipeline $pipeline,
    ) {
    }

    public function send(mixed $data): void
    {
        $this->channel->send(new JobMessage($this->id, $data));
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->pipeline->continue($cancellation);
    }
}
