# Tuicha

Simple ORM for MongoDB (PHP and HHVM).

## Installing

_This project is under heavy development, the APIs may change without any notice_.

Tuicha can be installed with [composer](https://getcomposer.org/).

```
composer require tuicha/tuicha:dev-develop
```

## Getting started

Tuicha is designed to be simple and friendly with every framework, that is why it uses and abuses of static methods.

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

#### Basic usage

Any object which uses the `Tuicha\Document` trait can be stored in MongoDB with Tuicha seamlessly.

```php
class Book {
  use Tuicha\Document;
}

$x = new Book;
$x->id = 1;
$x->name = "foobar";
$x->save(); // save
```

Finding document is quite straightforward as well:

```php
$books = Book::find(function($query) {
    $query->name = 'foobar';
});

foreach ($books as $book) {
    echo $book->name . "\n";
}
```


# TODO

1. Write more documentation
2. Add events support.
