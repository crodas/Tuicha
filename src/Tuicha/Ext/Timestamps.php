<?php

namespace Tuicha\Ext;

use Datetime;

trait Timestamps
{
    public $created_at;
    public $updated_at;

    /**
     * @before_create
     */
    public function _timestampsBeforeCreate()
    {
        $this->created_at = new Datetime;
        $this->updated_at = new Datetime;
    }

    /**
     * @before_update
     */
    public function _timestampsBeforeUpdate()
    {
        $this->updated_at = new Datetime;
    }
}
