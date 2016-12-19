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
    

    git clone https://github.com/mongodb/mongo-hhvm-driver.git --recursive
    cd mongo-hhvm-driver
    git checkout -f $DRIVER_VERSION
    
    cd libbson; ./autogen.sh > /dev/null; cd - 
    cd libmongoc ; ./autogen.sh > /dev/null; cd -
    
    hphpize
    cmake .
    make configlib
    make -j 4
    make install
    cd -
else
    phpenv config-rm xdebug.ini
    pecl install -f mongodb-${DRIVER_VERSION}
    php --ri mongodb
fi

composer install --dev --no-interaction --prefer-source
ulimit -c
ulimit -c unlimited -S
