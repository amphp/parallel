# Change log
All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).


## Unreleased
### Added
- You can now easily create worker pools that use a fixed worker type with new dedicated worker factories: `Worker\WorkerThreadFactory`, `Worker\WorkerForkFactory`, and `Worker\WorkerProcessFactory`.

### Changed
- Updated to Icicle `0.9.x` packages.

### Fixed
- Fixed bug where workers would begin throwing `BusyError`s when tasks are enqueued simultaneously or between multiple coroutines.
- Fixed bugs with worker shutdowns conflicting with tasks already running.
- Fixed race conditions with pools occurring when enqueuing many tasks at once.


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


[0.1.1]: https://github.com/icicleio/concurrent/releases/tag/v0.1.1
[0.1.0]: https://github.com/icicleio/concurrent/releases/tag/v0.1.0
[0.1.0-beta1]: https://github.com/icicleio/concurrent/releases/tag/v0.1.0-beta1
