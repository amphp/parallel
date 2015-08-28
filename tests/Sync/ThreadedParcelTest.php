<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\ThreadedParcel;

/**
 * @requires extension pthreads
 */
class ThreadedParcelTest extends AbstractParcelTest
{
    protected function createParcel($value)
    {
        return new ThreadedParcel($value);
    }
}
