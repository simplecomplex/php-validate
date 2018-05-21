<?php

namespace SimpleComplex\Tests\Validate;

/**
 * @package SimpleComplex\Tests\Validate
 */
class Stringable
{
    public $property;

    /**
     * @return string
     */
    public function __toString() : string
    {
        return serialize(get_object_vars($this));
    }
}
