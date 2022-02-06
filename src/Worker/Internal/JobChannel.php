<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Cancellation;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

/** @internal */
final class JobChannel implements Channel
{
    private bool $closed;

    public function __construct(
        private string $id,
        private Channel $channel,
        private ConcurrentIterator $iterator,
        private \Closure $cancel,
    ) {
        $this->channel->isClosed();
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
        $this->closed = true;
        $this->iterator->dispose();
        ($this->cancel)();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed() || $this->closed;
    }
}
