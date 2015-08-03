Vagrant.configure(2) do |config|
  config.vm.box = "coderstephen/php-dev"

  config.vm.provision "shell", inline: <<-SHELL
    newphp 56 zts
    sudo pickle install -n --ini=/etc/php56/php-cli.ini pthreads
  SHELL
end
