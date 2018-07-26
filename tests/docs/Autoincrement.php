<?php


/**
 * @persist(_autoincrement)
 */
class Autoincrement
{
    use Tuicha\Document;

    /** @String @Id */
    public $id;
    
    /** @Int */
    public $lastNumber;

    public static function next($name)
    {
        $results = static::update()
            ->where('_id', $name)
            ->lastNumber->add(1)
            ->findAndModify(true, true);

        return $results[0]->lastNumber;
    }
}
