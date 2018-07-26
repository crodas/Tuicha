<?php


/**
 * @persist(_autoincrement)
 */
class Autoincrement
{
    use Tuicha\Document;
    use Tuicha\Ext\AutoincrementID;

    /** @String @Id */
    public $id;
}
