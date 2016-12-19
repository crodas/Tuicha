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

if [ "$IS_HHVM" == "1" ]
then 
    FILE=${DRIVER_VERSION}.tar.gz
    DIR=mongo-hhvm-driver-${DRIVER_VERSION}
    wget https://github.com/mongodb/mongo-hhvm-driver/archive/${FILE}
    tar xfvz $FILE
    cd $DIR
    hphpize
    cmake .
    make configlib
    make -j 4
    make install
    cd -
else
    phpenv config-rm xdebug.ini
    pecl install -f mongodb-${DRIVER_VERSION}
    if dpkg --compare-versions ${SERVER_VERSION} le "2.4"; then export SERVER_SERVICE=mongodb; else export SERVER_SERVICE=mongod; fi
    php --ri mongodb
fi

composer install --dev --no-interaction --prefer-source
ulimit -c
ulimit -c unlimited -S
