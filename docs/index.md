---
title: Parallel
permalink: /
---
**True parallel processing using multiple processes or native threads for concurrent PHP code execution, *without* blocking, no extensions required.**

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/parallel
```

## Usage

This package provides native threading, multiprocessing, process synchronization, shared memory, and task workers for concurrently executing PHP code. To be as flexible as possible, this package includes a collection of non-blocking concurrency tools that can be used independently as needed, as well as an "opinionated" worker API that allows you to assign units of work to a pool of worker threads or processes.
