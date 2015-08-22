Vagrant.configure(2) do |config|
  config.vm.box = "coderstephen/php-dev"

  config.vm.provision "shell", inline: <<-SHELL
    newphp 56 zts debug

    # Install xdebug
    sudo pickle install -n xdebug
    echo 'zend_extension=xdebug.so' >> `php -i | grep php-cli.ini | awk '{print $5}'`

    # Install pthreads from master
    git clone https://github.com/krakjoe/pthreads
    cd pthreads
    git checkout master
    phpize
    ./configure
    make
    sudo make install
    echo 'extension=pthreads.so' >> `php -i | grep php-cli.ini | awk '{print $5}'`
  SHELL
end
