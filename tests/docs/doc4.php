<?php

namespace Docs;

/** @Persist("document4") */
class Doc4 {
    use \Tuicha\Document;
    use \Tuicha\Ext\Timestamps;

    /** @id */
    protected $Id;

    public function getId()
    {
        return $this->Id;
    }
}


