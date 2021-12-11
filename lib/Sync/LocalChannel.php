<?php

namespace Amp\Parallel\Sync;

use Amp\Cancellation;
use Amp\Pipeline\Emitter;
use Amp\Pipeline\Pipeline;

/**
 * @template TValue
 * @template-implements Channel<TValue>
 */
final class LocalChannel implements Channel
{
    /** @var Emitter<TValue> */
    private Emitter $emitter;

    /** @var Pipeline<TValue> */
    private Pipeline $pipeline;

    public function __construct(int $capacity = 0)
    {
        $this->emitter = new Emitter($capacity);
        $this->pipeline = $this->emitter->pipe();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isClosed(): bool
    {
        return $this->emitter->isComplete();
    }

    public function close(): void
    {
        if (!$this->emitter->isComplete()) {
            $this->emitter->complete();
        }
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->pipeline->continue($cancellation);
    }

    public function send(mixed $data): void
    {
        if ($data === null) {
            throw new ChannelException("Cannot send null on a channel");
        }

        if ($this->isClosed()) {
            throw new ChannelException("Cannot send on a closed channel");
        }

        $this->emitter->yield($data);
    }
}
