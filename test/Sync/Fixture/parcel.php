<?php

namespace Amp\Parallel\Test\Sync\Fixture;

use Amp\Parallel\Sync\SharedMemoryParcel;

return function () use ($argv): int {
    if (!isset($argv[1])) {
        throw new \Error('No parcel ID given');
    }

    $parcel = SharedMemoryParcel::use($argv[1]);

    $parcel->synchronized(function (int $value): int {
        return $value + 1;
    });

    return $parcel->unwrap();
};
