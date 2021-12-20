<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ClosableStream;
use Amp\Cancellation;
use Amp\Pipeline\Emitter;
use Amp\Pipeline\Pipeline;

/**
 * Creates a channel from a Pipeline and Emitter. The Pipeline emits data to be received on the channel (data
 * emitted on the Pipeline will be returned from calls to {@see Channel::receive()}). The Emitter will receive data
 * that sent on the channel (data passed to {@see Channel::send()} will be passed to {@see Emitter::yield()}).
 *
 * @template TReceive
 * @template TSend
 * @template-implements Channel<TReceive, TSend>
 */
final class PipelineChannel implements Channel, ClosableStream
{
    /**
     * @param Pipeline<TReceive> $receive
     * @param Emitter<TSend> $send
     */
    public function __construct(
        private Pipeline $receive,
        private Emitter $send,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isClosed(): bool
    {
        return $this->send->isComplete();
    }

    public function close(): void
    {
        if (!$this->send->isComplete()) {
            $this->send->complete();
        }

        $this->receive->dispose();
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->receive->continue($cancellation);
    }

    public function send(mixed $data): void
    {
        if ($data === null) {
            throw new ChannelException("Cannot send null on a channel");
        }

        if ($this->send->isComplete()) {
            throw new ChannelException("Cannot send on a closed channel");
        }

        $this->send->yield($data);
    }
}
