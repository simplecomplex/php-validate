<?php

declare(strict_types=1);

namespace SimpleComplex\Tests\Validate\Entity;

/**
 * @package SimpleComplex\Tests\Validate\Entity
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
