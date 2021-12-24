<?php

namespace Amp\Parallel\Sync;

use Amp\Sync\Semaphore;

/**
 * @template T
 * @template-implements Parcel<T>
 */
final class LocalParcel implements Parcel
{
    /**
     * @param Semaphore $semaphore
     * @param T $value
     */
    public function __construct(
        private Semaphore $semaphore,
        private mixed $value,
    ) {
    }

    public function synchronized(\Closure $closure): mixed
    {
        $lock = $this->semaphore->acquire();

        try {
            $this->value = $closure($this->value);
        } finally {
            $lock->release();
        }

        return $this->value;
    }

    public function unwrap(): mixed
    {
        return $this->value;
    }
}
