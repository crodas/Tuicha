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
    sudo apt-get install hhvm-dev hhvm-dbg
    FILE=hhvm-mongodb-${DRIVER_VERSION}.tgz
    wget https://github.com/mongodb/mongo-hhvm-driver/releases/download/1.2.0/${FILE}
    tar xfz $FILE
    cd hhvm-mongodb-${DRIVER_VERSION}


    hphpize
    cmake .
    make configlib
    make -j 4
    sudo cp mongodb.so /etc/hhvm
    echo 'hhvm.dynamic_extensions[mongodb]=/etc/hhvm/mongodb.so' | sudo tee --append /etc/hhvm/php.ini > /dev/null
    cd ..
else
    phpenv config-rm xdebug.ini
    pecl install -f mongodb-${DRIVER_VERSION}
    php --ri mongodb
fi

wget https://phar.phpunit.de/phpunit-5.7.phar
mv phpunit-5.7.phar `which phpunit`
chmod +x `which phpunit`

composer install --dev --no-interaction --prefer-source
ulimit -c
ulimit -c unlimited -S
