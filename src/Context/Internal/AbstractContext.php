<?php declare(strict_types=1);

namespace Amp\Parallel\Context\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use function Amp\Parallel\Context\flattenArgument;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-implements Context<TResult, TReceive, TSend>
 */
abstract class AbstractContext implements Context
{
    use ForbidCloning;
    use ForbidSerialization;

    protected function __construct(
        private readonly Channel $channel,
    ) {
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        try {
            $data = $this->channel->receive($cancellation);
        } catch (ChannelException $exception) {
            try {
                $data = $this->join(new TimeoutCancellation(0.1));
            } catch (ChannelException|CancelledException) {
                if (!$this->isClosed()) {
                    $this->close();
                }
                throw new ContextException(
                    "The context stopped responding, potentially due to a fatal error or calling exit",
                    previous: $exception,
                );
            }

            throw new ContextException(\sprintf(
                'Context unexpectedly exited when waiting to receive data with result: %s',
                flattenArgument($data),
            ), previous: $exception);
        }

        if (!$data instanceof ContextMessage) {
            if ($data instanceof ExitResult) {
                $data = $data->getResult();

                throw new ContextException(\sprintf(
                    'Context unexpectedly exited when waiting to receive data with result: %s',
                    flattenArgument($data),
                ));
            }

            throw new ContextException(\sprintf(
                'Unexpected data type from context: %s',
                flattenArgument($data),
            ));
        }

        return $data->getMessage();
    }

    public function send(mixed $data): void
    {
        try {
            $this->channel->send($data);
        } catch (ChannelException $exception) {
            try {
                $data = $this->join(new TimeoutCancellation(0.1));
            } catch (ChannelException|CancelledException) {
                if (!$this->isClosed()) {
                    $this->close();
                }

                throw new ContextException(
                    "The context stopped responding, potentially due to a fatal error or calling exit",
                    previous: $exception,
                );
            }

            throw new ContextException(\sprintf(
                'Context unexpectedly exited when sending data with result: %s',
                flattenArgument($data),
            ), 0, $exception);
        }
    }

    public function close(): void
    {
        $this->channel->close();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->channel->onClose($onClose);
    }

    protected function receiveExitResult(?Cancellation $cancellation = null): ExitResult
    {
        if ($this->channel->isClosed()) {
            throw new ContextException("The context has already closed without providing a result");
        }

        try {
            $data = $this->channel->receive($cancellation);
        } catch (CancelledException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if (!$this->isClosed()) {
                $this->close();
            }
            throw new ContextException("Failed to receive result from context", previous: $exception);
        }

        if (!$data instanceof ExitResult) {
            if (!$this->isClosed()) {
                $this->close();
            }

            if ($data instanceof ContextMessage) {
                $data = $data->getMessage();
            }

            throw new ContextException(\sprintf(
                "The context sent data instead of exiting: %s",
                flattenArgument($data),
            ));
        }

        $this->channel->close();

        return $data;
    }
}
