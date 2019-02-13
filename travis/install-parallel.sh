#!/usr/bin/env bash

git clone https://github.com/krakjoe/parallel;
pushd parallel;
phpize;
./configure;
make;
make install;
popd;
echo "extension=parallel.so" >> "$(php -r 'echo php_ini_loaded_file();')"
