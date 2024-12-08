<?php

spl_autoload_register(function (string $className) {
    if ($className !== CustomAutoloadClass::class) {
        return;
    }

    class CustomAutoloadClass
    {
    }
});
