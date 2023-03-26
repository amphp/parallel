<?php declare(strict_types=1);

namespace Amp\Parallel\Context\Internal;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use function Amp\async;
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

    /** @var Future<ExitResult> */
    private readonly Future $result;

    protected function __construct(
        private readonly Channel $ipcChannel,
        private readonly Channel $resultChannel,
    ) {
        $this->result = async(static function () use ($resultChannel, $ipcChannel): ExitResult {
            try {
                $data = $resultChannel->receive();
            } catch (\Throwable $exception) {
                throw new ContextException("Failed to receive result from context", previous: $exception);
            } finally {
                $resultChannel->close();
                $ipcChannel->close();
            }

            if (!$data instanceof ExitResult) {
                throw new ContextException(\sprintf(
                    "The context sent data instead of exiting: %s",
                    flattenArgument($data),
                ));
            }

            return $data;
        });

        $this->result->ignore();
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        try {
            $data = $this->ipcChannel->receive($cancellation);
        } catch (ChannelException $exception) {
            $this->ipcChannel->close();

            throw new ContextException(
                "The context stopped responding, potentially due to a fatal error or calling exit",
                previous: $exception,
            );
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
            $this->ipcChannel->send($data);
        } catch (ChannelException $exception) {
            $this->ipcChannel->close();

            throw new ContextException(
                "The context stopped responding, potentially due to a fatal error or calling exit",
                previous: $exception,
            );
        }
    }

    public function close(): void
    {
        $this->ipcChannel->close();
        $this->resultChannel->close();
    }

    public function isClosed(): bool
    {
        return $this->resultChannel->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->resultChannel->onClose($onClose);
    }

    protected function receiveExitResult(?Cancellation $cancellation = null): ExitResult
    {
        return $this->result->await($cancellation);
    }
}
