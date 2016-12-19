<?php

namespace Tuicha;

class Validation
{
    public static function is_email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function is_integer(&$number)
    {
        if (!is_numeric($number)) {
            return false;
        }
        $number = (int)$number;
        return true;
    }

}
