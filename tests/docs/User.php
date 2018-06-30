<?php

/**
 * @singlecollection
 */
class User
{
    use Tuicha\Document;

    /** @Required @Index(sparse=true) */
    public $name;

    /** @Validate (is_email) @Unique */
    public $email;

    /**
     * @Validate(is_integer, @between(0, 99), "CustomValidator::isInt")
     */
    public $age;

    /** @Array */
    public $array;

    /** @Array(@Int) */
    public $karma;

    /** @reference(with=['email'], readonly=true) */
    public $ref;

    /**  @class(User::class) */
    protected $another_user;

    public function t()
    {
        static $val;
        if (!$val) {
            $val = uniqid();
        }
        return $val;
    }

    public function addAnotherUser(User $user)
    {
        $this->another_user = $user;
    }

    public function getAnotherUser()
    {
        return $this->another_user;
    }

    public function scopeTeens($query)
    {
        return $query->where('age', '>', 18)
            ->where('age', '<', 30);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
