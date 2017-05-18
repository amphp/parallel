<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Parallel\Test\Sync\AbstractParcelTest;
use Amp\Parallel\Threading\Parcel;

/**
 * @requires extension pthreads
 */
class ParcelTest extends AbstractParcelTest {
    protected function createParcel($value) {
        return new Parcel($value);
    }
}
