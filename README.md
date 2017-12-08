<p align="center">
<a href="https://amphp.org/parallel"><img src="https://raw.githubusercontent.com/amphp/logo/master/repos/parallel.png?v=12-07-2017" alt="parallel"/></a>
</p>

<p align="center">
<a href="https://travis-ci.org/amphp/parallel"><img src="https://img.shields.io/travis/amphp/parallel/master.svg?style=flat-square" alt="Build Status"/></a>
<a href="https://coveralls.io/github/amphp/parallel?branch=master"><img src="https://img.shields.io/coveralls/amphp/parallel/master.svg?style=flat-square" alt="Code Coverage"/></a>
<a href="https://github.com/amphp/parallel/releases"><img src="https://img.shields.io/github/release/amphp/parallel.svg?style=flat-square" alt="Release"/></a>
<a href="https://github.com/amphp/parallel/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="License"/></a>
</p>

<p align="center"><strong>True parallel processing using multiple processes or native threads for concurrent PHP code execution, <em>without</em> blocking, no extensions required.</strong></p>

<hr/>

`amphp/parallel` a component for [Amp](https://amphp.org) that provides native threading, multiprocessing, process synchronization, shared memory, and task workers for concurrently executing PHP code. Like other Amp components, this library uses [Coroutines](http://amphp.org/amp/coroutines/) built from [Promises](http://amphp.org/amp/promises/) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

To be as flexible as possible, this library comes with a collection of non-blocking concurrency tools that can be used independently as needed, as well as an "opinionated" worker API that allows you to assign units of work to a pool of worker threads or processes.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/parallel
```

## Requirements

- PHP 7.0+ (no extensions required)

## Documentation

Documentation can be found on [amphp.org/parallel](https://amphp.org/parallel/) as well as in the [`./docs`](./docs) directory.

## Versioning

`amphp/parallel` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.

## Development and Contributing

Want to hack on the source? A [Vagrant](http://vagrantup.com) box is provided with the repository to give a common development environment for running concurrent threads and processes, and comes with a bunch of handy tools and scripts for testing and experimentation.

Starting up and logging into the virtual machine is as simple as

    vagrant up && vagrant ssh

Once inside the VM, you can install PHP extensions with [Pickle](https://github.com/FriendsOfPHP/pickle), switch versions with `newphp VERSION`, and test for memory leaks with [Valgrind](http://valgrind.org).
