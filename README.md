# Tuicha

Simple ORM for MongoDB (PHP and HHVM).

## Installing

_This project is under heavy development, the APIs may change without any notice_.

Tuicha can be installed with [composer](https://getcomposer.org/).

```
composer require tuicha/tuicha:dev-develop
```

## Getting started

Tuicha is designed to be simple and friendly with every framework, that is why it uses and abuses with static methods.

### Creating a conection

```php
Tuicha::addConnection("tuicha_testsuite");
```

_By default all conections to localhost_

If the MongoDB server is running in another machine it must be specified in the second argument.

```php
Tuicha::addConnection("tuicha_testsuite", "mongodb://8.8.8.8:27017");
```

### Defining models

#### Basic definition

Any object which uses the `Tuicha\Document` trait can be stored in MongoDB with Tuicha.

```php 
class Books {
  use Tuicha\Document;
}

$x = new Books;
$x->name = "foobar";
$x->save(); // save

var_dump($x->id); // Object ID
```

Any property (defined or not in the class definition) will be stored in MongoDB.

# TODO

1. Write more documentation
2. Add events support.
