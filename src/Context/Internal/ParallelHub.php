<?php declare(strict_types=1);

namespace Amp\Parallel\Context\Internal;

use Amp\ByteStream\StreamChannel;
use parallel\Events;
use parallel\Future;
use Revolt\EventLoop;

/** @internal */
final class ParallelHub
{
    private const EXIT_CHECK_FREQUENCY = 0.25;

    /** @var StreamChannel[] */
    private array $channels = [];

    private string $watcher;

    /** @psalm-suppress UndefinedClass */
    private Events $events;

    public function __construct()
    {
        /** @psalm-suppress UndefinedClass */
        $events = $this->events = new Events;
        $this->events->setBlocking(false);

        $channels = &$this->channels;
        $this->watcher = EventLoop::repeat(self::EXIT_CHECK_FREQUENCY, static function () use (&$channels, $events): void {
            while ($event = $events->poll()) {
                $id = (int) $event->source;
                \assert(isset($channels[$id]), 'Channel for context ID not found');
                $channel = $channels[$id];
                unset($channels[$id]);
                $channel->close();
            }
        });
        EventLoop::disable($this->watcher);
        EventLoop::unreference($this->watcher);
    }

    /** @psalm-suppress UndefinedClass */
    public function add(int $id, StreamChannel $channel, Future $future): void
    {
        $this->channels[$id] = $channel;
        $this->events->addFuture((string) $id, $future);

        EventLoop::enable($this->watcher);
    }

    public function remove(int $id): void
    {
        if (!isset($this->channels[$id])) {
            return;
        }

        unset($this->channels[$id]);
        $this->events->remove((string) $id);

        if (empty($this->channels)) {
            EventLoop::disable($this->watcher);
        }
    }
}
