#!/bin/bash -x

# Install MongoDB
sudo apt-key adv --keyserver ${KEY_SERVER} --recv 7F0CEB10
sudo apt-key adv --keyserver ${KEY_SERVER} --recv EA312927
echo "deb ${MONGO_REPO_URI} ${MONGO_REPO_TYPE}${SERVER_VERSION} multiverse" | sudo tee ${SOURCES_LOC}
sudo apt-get update -qq

if dpkg --compare-versions ${SERVER_VERSION} le "2.4"; then export SERVER_PACKAGE=mongodb-10gen-enterprise; else export SERVER_PACKAGE=mongodb-enterprise; fi
sudo apt-get install ${SERVER_PACKAGE}
sudo apt-get -y install gdb


# Start MongoDB
if dpkg --compare-versions ${SERVER_VERSION} le "2.4"; then export SERVER_SERVICE=mongodb; else export SERVER_SERVICE=mongod; fi
if ! nc -z localhost 27017; then sudo service ${SERVER_SERVICE} start; fi
mongod --version

if [[ $TRAVIS_PHP_VERSION =~ ^hhvm ]]
then
    sudo add-apt-repository ppa:ubuntu-toolchain-r/test -y
    sudo add-apt-repository ppa:boost-latest/ppa -y
    
    sudo add-apt-repository ppa:mapnik/boost -y
    sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0x5a16e7281be7a449
    
    sudo apt-get update
    sudo apt-get install gcc-4.8 g++-4.8 libboost1.55-all-dev hhvm-dev -qqy 
    sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-4.8 60 \
                         --slave /usr/bin/g++ g++ /usr/bin/g++-4.8
    sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-4.6 40 \
                         --slave /usr/bin/g++ g++ /usr/bin/g++-4.6
    sudo update-alternatives --set gcc /usr/bin/gcc-4.8
    
    wget http://launchpadlibrarian.net/80433359/libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    sudo dpkg -i libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    rm libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    wget http://launchpadlibrarian.net/80433361/libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb
    sudo dpkg -i libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb
    rm libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb

    # Install libjemalloc
    wget http://ubuntu.mirrors.tds.net/ubuntu/pool/universe/j/jemalloc/libjemalloc1_3.6.0-2_amd64.deb
    sudo dpkg -i libjemalloc1_3.6.0-2_amd64.deb
    rm libjemalloc1_3.6.0-2_amd64.deb

    wget http://ubuntu.mirrors.tds.net/ubuntu/pool/universe/j/jemalloc/libjemalloc-dev_3.6.0-2_amd64.deb
    sudo dpkg -i libjemalloc-dev_3.6.0-2_amd64.deb
    rm libjemalloc-dev_3.6.0-2_amd64.deb
    
    
    FILE=hhvm-mongodb-${DRIVER_VERSION}.tgz
    wget https://github.com/mongodb/mongo-hhvm-driver/releases/download/1.2.0/${FILE}
    tar xfz $FILE
    cd hhvm-mongodb-${DRIVER_VERSION}


    hphpize
    cmake .
    make configlib
    make 
    sudo cp mongodb.so /etc/hhvm
    echo 'hhvm.dynamic_extensions[mongodb]=/etc/hhvm/mongodb.so' | sudo tee --append /etc/hhvm/php.ini > /dev/null
    cd ..
else
    phpenv config-rm xdebug.ini
    pecl install -f mongodb-${DRIVER_VERSION}
    php --ri mongodb
fi

composer install --dev --no-interaction --prefer-source
ulimit -c
ulimit -c unlimited -S
