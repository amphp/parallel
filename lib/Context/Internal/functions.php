<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ExitFailure;
use Amp\Parallel\Sync\ExitResult;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use function Amp\call;

function loadCallable(string $path)
{
    return require $path;
}

function sendResult(Channel $channel, ExitResult $result): Promise
{
    return call(function () use ($channel, $result) {
        try {
            yield $channel->send($result);
        } catch (SerializationException $exception) {
            // Serializing the result failed. Send the reason why.
            yield $channel->send(new ExitFailure($exception));
        }
    });
}
