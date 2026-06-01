<?php declare(strict_types=1);

namespace Amp\Parallel\Context\Internal;

use Amp\DeferredFuture;
use Amp\Future as AmpFuture;
use Amp\Interval;
use parallel\Events;
use parallel\Future as ParallelFuture;
use function Amp\weakClosure;

/** @internal */
final class ParallelHub
{
    private const EXIT_CHECK_FREQUENCY = 0.25;

    /** @var array<int, DeferredFuture> */
    private array $deferredFutures = [];

    private readonly Interval $interval;

    private readonly Events $events;

    public function __construct()
    {
        $this->events = new Events();
        $this->events->setBlocking(false);

        $this->interval = new Interval(self::EXIT_CHECK_FREQUENCY, weakClosure(function (): void {
            while ($event = $this->events->poll()) {
                $id = (int) $event->source;
                \assert(isset($this->deferredFutures[$id]), 'Deferred future for context ID not found');
                $deferredFuture = $this->deferredFutures[$id];
                unset($this->deferredFutures[$id]);
                $deferredFuture->complete();
            }

            if (empty($this->deferredFutures)) {
                $this->interval->disable();
            }
        }), reference: false);

        $this->interval->disable();
    }

    public function add(int $id, ParallelFuture $future): AmpFuture
    {
        $this->deferredFutures[$id] = $deferred = new DeferredFuture();
        $this->events->addFuture((string) $id, $future);

        $this->interval->enable();

        return $deferred->getFuture();
    }

    public function remove(int $id): void
    {
        $deferred = $this->deferredFutures[$id] ?? null;
        if (!$deferred) {
            return;
        }

        if (!$deferred->isComplete()) {
            $deferred->complete();
        }

        unset($this->deferredFutures[$id]);
        $this->events->remove((string) $id);

        if (empty($this->deferredFutures)) {
            $this->interval->disable();
        }
    }
}
