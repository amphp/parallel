<?php

namespace Amp\Tests\Concurrent\Threading;

use Amp\Concurrent\Threading\Parcel;
use Amp\Tests\Concurrent\Sync\AbstractParcelTest;

/**
 * @requires extension pthreads
 */
class ParcelTest extends AbstractParcelTest
{
    protected function createParcel($value)
    {
        return new Parcel($value);
    }
}
