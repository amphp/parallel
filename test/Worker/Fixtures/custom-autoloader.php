<?php

\spl_autoload_register(function () {
    if (!\defined("AMP_TEST_CUSTOM_AUTOLOADER")) {
        \define("AMP_TEST_CUSTOM_AUTOLOADER", true);
    }
});
