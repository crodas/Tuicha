<?php

namespace Tuicha;

use Tuicha;

class Reference
{
    protected $ref;
    protected $id;
    protected $class;

    public function __construct(array $reference)
    {
        $this->ref   = $reference['$ref'];
        $this->id    = $reference['$id'];
        $this->class = Tuicha::getCollectionClass($reference['$ref']);
    }
    
    public function getObject()
    {
        return Tuicha::find($this->ref, ['_id' => $this->id])->first();
    }

    public function __get($name)
    {
        return $this->getObject()->$name;
    }
}
