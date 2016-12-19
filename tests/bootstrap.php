<?php

require __DIR__ . '/../vendor/autoload.php';

Remember\Remember::setDirectory(__DIR__ . '/tmp');

Tuicha::addConnection("tuicha_testsuite");
Tuicha::loadDocuments(__DIR__ . '/docs');

Tuicha::dropDatabase();
