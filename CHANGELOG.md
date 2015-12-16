# Change log
All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).

## [0.2.1] - 2015-12-16
### Added
- Added `Icicle\Concurrent\Worker\DefaultQueue` implementing `Icicle\Concurrent\Worker\Queue` that provides a queue of workers that can be pulled and pushed from the queue as needed. Pulling a worker marks it as busy and pushing the worker back into the queue marks it as idle. If no idle workers remain in the queue, a worker is selected from those marked as busy. A worker queue allows a set of interdependent tasks (for example, tasks that depend on an environment value in the worker) to be run on a single worker without having to create and start separate workers for each task.

### Fixed
- Fixed bug where exit status was not being read in `Icicle\Concurrent\Process\Process`, which also caused `Icicle\Concurrent\Worker\WorkerProcess` to fail.

## [0.2.0] - 2015-12-13
### Changed
- Updated to Icicle `0.9.x` packages.
- All exceptions now implement the `Icicle\Exception\Throwable` interface.
- All interface names have been changed to remove the Interface suffix.
- `Sync\Channel` was renamed to `Sync\ChannelledStream`.
- `Sync\Parcel` was renamed to `Sync\SharedMemoryParcel`.
- `Worker\Worker` has been renamed to `Worker\AbstractWorker`.
- `Worker\Pool` has been renamed to `Worker\DefaultPool`.
- `Worker\WorkerFactory` is now an interface, with the default implementation as `Worker\DefaultWorkerFactory`.

### Fixed
- Fixed bug where workers would begin throwing `BusyError`s when tasks are enqueued simultaneously or between multiple coroutines.
- Fixed bugs with worker shutdowns conflicting with tasks already running.
- Fixed race conditions with pools occurring when enqueuing many tasks at once.
- Fixed issue with contexts failing without explanation if the returned value could not be serialized.


## [0.1.1] - 2015-11-13
### Added
- Runtime support for forks and threads can now be checked with `Forking\Fork::enabled()` and `Threading\Thread::enabled()`, respectively.

### Changed
- Creating a fork will now throw an `UnsupportedError` if forking is not available.
- Creating a thread will now throw an `UnsupportedError` if threading is not available.
- Creating a `Sync\Parcel` will now throw an `UnsupportedError` if the `shmop` extension is not enabled.
- Creating a `Sync\PosixSemaphore` will now throw an `UnsupportedError` if the `sysvmsg` extension is not enabled.

### Fixed
- Fixed `Worker\Pool::__construct()` using `$minSize` as the maximum pool size instead.
- `Process\Process` no longer reports as running during process destruction after calling `kill()`.


## [0.1.0] - 2015-10-28
### Changed
- `Sync\ParcelInterface::wrap()` was removed in favor of `synchronized()`, which now passes the value of the parcel to the callback function. The value returned by the callback will be wrapped.
- Both channel interfaces were combined into `Sync\Channel`.
- `ContextInterface` no longer extends a channel interface
- `Forking\Fork` and `Process\Process` now implement `ProcessInterface`.
- Updated `icicleio/stream` to v0.4.1.

### Fixed
- Fixed issue with error handler in `Sync\Channel` catching unrelated errors until the next tick.


## [0.1.0-beta1] - 2015-09-28
First release.

### Added
- Creating and controlling multiple threads with `Threading\Thread`.
- Creating and controlling multiple forked processes with `Forking\Fork`.
- Workers and tasks, which can use either threading, forks, or a separate PHP process.
- A global worker pool that any tasks can be run in.
- Channels for sending messages across execution contexts.
- Parcels for storing values and objects in shared memory locations for use across contexts.
- Non-blocking mutexes and semaphores for protecting parcels.


[0.2.0]: https://github.com/icicleio/concurrent/releases/tag/v0.2.0
[0.1.1]: https://github.com/icicleio/concurrent/releases/tag/v0.1.1
[0.1.0]: https://github.com/icicleio/concurrent/releases/tag/v0.1.0
[0.1.0-beta1]: https://github.com/icicleio/concurrent/releases/tag/v0.1.0-beta1
