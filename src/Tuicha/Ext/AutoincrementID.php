<?php

namespace Tuicha\Ext;

use Tuicha\Metadata;
use Tuicha\Document;

/**
 * @Persist(__autoincrement)
 */
class AutoincrementProvider
{
    use Document;

    /** @String @Id */
    public $id;
    
    /** @Int */
    public $lastNumber;

    public static function next($name)
    {
        return static::update()
            ->where('id', $name)
            ->lastNumber->add(1)
            ->findAndModify(true, true)[0]->lastNumber;
    }
}

trait AutoincrementID
{
    /**
     * @before_create
     */
    public function set_autoincrement_id()
    {
        $metadata   = Metadata::of($this);
        $collection = $metadata->getCollection();
        $metadata->setId($this, AutoincrementProvider::next($collection));
    }
}
