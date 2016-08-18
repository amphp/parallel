<?php

namespace Amp\Concurrent\Test\Threading;

use Amp\Concurrent\Threading\Parcel;
use Amp\Concurrent\Test\Sync\AbstractParcelTest;

/**
 * @requires extension pthreads
 */
class ParcelTest extends AbstractParcelTest {
    protected function createParcel($value) {
        return new Parcel($value);
    }
}
