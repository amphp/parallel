<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Cancellation;
use Amp\Parallel\Context\Context;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

/** @internal */
final class JobChannel implements Channel
{
    public function __construct(
        private string $id,
        private Context|Channel $channel,
        private ConcurrentIterator $iterator,
        private \Closure $cancel,
    ) {
    }

    public function send(mixed $data): void
    {
        $this->channel->send(new JobMessage($this->id, $data));
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        if (!$this->iterator->continue($cancellation)) {
            throw new ChannelException('Channel source closed unexpectedly');
        }

        return $this->iterator->getValue();
    }

    public function close(): void
    {
        $this->iterator->dispose();
        ($this->cancel)();
    }

    public function isClosed(): bool
    {
        return !$this->channel->isRunning();
    }
}
