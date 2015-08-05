Vagrant.configure(2) do |config|
  config.vm.box = "coderstephen/php-dev"

  config.vm.provision "shell", inline: <<-SHELL
    newphp 56 zts
    sudo pickle install -n xdebug
    echo 'zend_extension=xdebug.so' >> `php -i | grep php-cli.ini | awk '{print $5}'`
    sudo pickle install -n pthreads
    echo 'extension=pthreads.so' >> `php -i | grep php-cli.ini | awk '{print $5}'`
  SHELL
end
