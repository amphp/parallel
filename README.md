# amphp/parallel

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/parallel` provides *true parallel processing* for PHP using multiple processes, *without blocking and no extensions required*.

To be as flexible as possible, this library comes with a collection of non-blocking concurrency tools that can be used independently as needed, as well as an "opinionated" worker API that allows you to assign units of work to a pool of worker processes.

[![Latest Release](https://img.shields.io/github/release/amphp/parallel.svg?style=flat-square)](https://github.com/amphp/parallel/releases)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://github.com/amphp/parallel/blob/master/LICENSE)


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

$executions = [];
foreach ($urls as $url) {
    // FetchTask is just an example, you'll have to implement
    // the Task interface for your task.
    $executions[$url] = Worker\submit(new FetchTask, $url);
}

// Each submission returns an Execution instance to allow two-way
// communication with a task. Here we're only interested in the
// task result, so we use the Future from Execution::getResult()
$responses = Future\await(array_map(
    fn (Worker\Execution $e => $e->getResult(),
    $executions,
));

foreach ($responses as $url => $response) {
    \printf("Read %d bytes from %s\n", \strlen($response), $url);
}
```

`FetchTask` is just used as an example for a blocking function here.
If you just want to fetch multiple HTTP resources concurrently, it's better to use [`amphp/http-client`](https://amphp.org/http-client/), our non-blocking HTTP client.

> **Note**
> The functions you call must be predefined or autoloadable by Composer, so they also exist in the worker processes.

### Workers

`Worker` provides a simple interface for executing PHP code in parallel in a separate PHP process.
Classes implementing [`Task`](#tasks) are used to define the code to be run in parallel.

### Tasks

The `Task` interface has a single `run()` method that gets invoked in the worker to dispatch the work that needs to be done.
The `run()` method can be written using blocking code since the code is executed in a separate process.

Task instances are `serialize`'d in the main process and `unserialize`'d in the worker.
That means that all data that is passed between the main process and a worker needs to be serializable.

### Worker Pools

The easiest way to use workers is through a worker pool.
Worker pools can be used to submit tasks in the same way as a worker, but rather than using a single worker process, the pool uses multiple workers to execute tasks.
This allows multiple tasks to be executed simultaneously.

The `WorkerPool` interface extends [`Worker`](#workers), adding methods to get information about the pool or pull a single `Worker` instance out of the pool.
A pool uses multiple `Worker` instances to execute [`Task`](#tasks) instances.

If a set of tasks should be run within a single worker, use the `WorkerPool::getWorker()` method to pull a single worker from the pool.
The worker is automatically returned to the pool when the instance returned is destroyed.

#### Global Worker Pool

A global worker pool is available and can be set using the function `Amp\Parallel\Worker\workerPool(?WorkerPool $pool = null)`.
Passing an instance of `WorkerPool` will set the global pool to the given instance.
Invoking the function without an instance will return the current global instance.

### Child Processes

The `ProcessContext` class simplifies writing and running PHP in parallel.
A script written to be run in parallel must return a callable that will be run in a child process.
The callable receives a single argument – an instance of `Channel` that can be used to send data between the parent and child processes. Any serializable data can be sent across this channel.
The `Context` object, which extends the `Channel` interface, is the other end of the communication channel.

In the example below, a child process is used to call a blocking function (`file_get_contents()` is only an example of a blocking function, use [`http-client`](https://amphp.org/http-client) for non-blocking HTTP requests).
The result of that function is then sent back to the parent using the `Channel` object.
The return value of the child process callable is available using the `Context::join()` method.

#### Child Process

```php
# child.php

use Amp\Sync\Channel;

return function (Channel $channel): mixed {
    $url = $channel->receive();

    $data = file_get_contents($url); // Example blocking function

    $channel->send($data);

    return 'Any serializable data';
};
```

#### Parent Process

```php
# parent.php

use Amp\Parallel\Context\ProcessContext;

// Creates and starts a child process context using ProcessContext
$context = ProcessContext::start(__DIR__ . '/child.php');

$url = 'https://google.com';
$context->send($url);

$requestData = $context->receive();
printf("Received %d bytes from %s\n", \strlen($requestData), $url);

$returnValue = $context->join();
printf("Child processes exited with '%s'\n", $returnValue);
```

Child processes are also great for CPU-intensive operations such as image manipulation or for running daemons that perform periodic tasks based on input from the parent.

### Context creation

An execution context can be created using the function `Amp\Parallel\Context\startContext()`, which uses the global `ContextFactory`. The global factory is an instance of `DefaultContextFactory` by default, but this instance can be overridden using the function `Amp\Parallel\Context\contextFactory()`.

Context factories are used by worker pools to create the context which executes tasks. Providing a custom `ContextFactory` to a worker pool allows custom bootstrapping or other behavior within pool workers.

An execution context can be created by a `ContextFactory`. The worker pool uses context factories to create workers.

A global worker pool is available and can be set using the function `Amp\Parallel\Worker\workerPool(?WorkerPool $pool = null)`.
Passing an instance of `WorkerPool` will set the global pool to the given instance.
Invoking the function without an instance will return the current global instance.

## Versioning

`amphp/parallel` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please use the private security issue reporter instead of using the public issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
