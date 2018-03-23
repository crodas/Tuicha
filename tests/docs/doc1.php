<?php

namespace Docs;

/** @Persist("document1") */
class Doc1 extends DocBase
{
    /** @Validate(docs\custom_validator) */
    public $x;

    /** @reference */
    public $user;
}

function custom_validator($value)
{
    return $value !== 'invalid';
}
