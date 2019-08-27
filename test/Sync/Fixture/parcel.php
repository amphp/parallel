<?php

namespace Amp\Parallel\Test\Sync\Fixture;

use Amp\Parallel\Sync\SharedMemoryParcel;

return function () use ($argv): \Generator {
    if (!isset($argv[1])) {
        throw new \Error('No parcel ID given');
    }

    $parcel = SharedMemoryParcel::use($argv[1]);

    yield $parcel->synchronized(function (int $value): int {
        return $value + 1;
    });

    return yield $parcel->unwrap();
};
