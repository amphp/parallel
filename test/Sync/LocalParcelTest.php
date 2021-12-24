<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\LocalParcel;
use Amp\Parallel\Sync\Parcel;
use Amp\Sync\LocalSemaphore;

class LocalParcelTest extends AbstractParcelTest
{
    protected function createParcel(mixed $value): Parcel
    {
        return new LocalParcel(new LocalSemaphore(1), $value);
    }
}
