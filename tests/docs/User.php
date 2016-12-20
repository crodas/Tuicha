<?php

class User {
    use Tuicha\Document;

    /** @Required */
    public $name;

    /** @Validate(is_email) */
    public $email;
}
