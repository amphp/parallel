<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Threading\Parcel;
use Icicle\Tests\Concurrent\Sync\AbstractParcelTest;

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
