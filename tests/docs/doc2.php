<?php

namespace Docs;

/** @Persist("document2") */
class Doc2 extends DocBase
{
    /** @Array */
    public $array;

    /** @Hash */
    public $hash;
}
