# amphp/parallel

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/parallel` provides *true parallel processing* for PHP using multiple processes or native threads, *without blocking and no extensions required*.

To be as flexible as possible, this library comes with a collection of non-blocking concurrency tools that can be used independently as needed, as well as an "opinionated" worker API that allows you to assign units of work to a pool of worker threads or processes.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/parallel
```

## Usage

The basic usage of this library is to submit blocking tasks to be executed by a worker pool in order to avoid blocking the main event loop.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Future;
use Amp\Parallel\Worker;
use function Amp\async;

$urls = [
    'https://secure.php.net',
    'https://amphp.org',
    'https://github.com',
];

$futures = [];
foreach ($urls as $url) {
    // FetchTask is just an example, you'll have to implement the Task interface for your task
    $futures[$url] = async(fn () => Worker\submit(new FetchTask, $url));
}

$responses = Future\await($futures);

foreach ($responses as $url => $response) {
    \printf("Read %d bytes from %s\n", \strlen($response), $url);
}
```

`FetchTask` is just used as an example for a blocking function here.
If you just want to fetch multiple HTTP resources concurrently, it's better to use [`amphp/http-client`](https://amphp.org/http-client/), our non-blocking HTTP client.

The functions you call must be predefined or autoloadable by Composer, so they also exist in the worker processes.

## Versioning

`amphp/parallel` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
