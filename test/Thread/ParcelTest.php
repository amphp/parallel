<?php

namespace Amp\Parallel\Test\Thread;

use Amp\Parallel\Test\Sync\AbstractParcelTest;
use Amp\Parallel\Thread\Parcel;

/**
 * @requires extension pthreads
 */
class ParcelTest extends AbstractParcelTest {
    protected function createParcel($value) {
        return new Parcel($value);
    }
}
