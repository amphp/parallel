<?php

// The function returned by this script is run by process.php in a separate process.
// echo, print, printf, etc. in this script are written to STDERR of the parent.
// $argc and $argv are available in this process as any other cli PHP script.

use Amp\Parallel\Sync\Channel;

return function (Channel $channel): \Generator {
    printf("Received the following from parent: %s\n", yield $channel->receive());

    print "Sleeping for 3 seconds...\n";
    sleep(3); // Blocking call in process.

    yield $channel->send("Data sent from child.");

    print "Sleeping for 2 seconds...\n";
    sleep(2); // Blocking call in process.

    return 42;
};
