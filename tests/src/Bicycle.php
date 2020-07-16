<?php

namespace SimpleComplex\Tests\Validate;

/**
 * @package SimpleComplex\Tests\Validate
 */
class Bicycle
{
    public $wheels = 0;
    public $saddle;
    public $sound = '';
    public $accessories = [];
    public $various;

    public function __construct($wheels, $saddle, $sound, $accessories, $various)
    {
        $this->wheels = $wheels;
        $this->saddle = $saddle;
        $this->sound = $sound;
        $this->accessories = $accessories;
        $this->various = $various;
    }
}
