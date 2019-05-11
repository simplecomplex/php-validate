<?php

namespace SimpleComplex\Tests\Validate;

/**
 * @package SimpleComplex\Tests\Validate
 */
class Stringable
{
    public $property;

    /**
     * No properties: empty string.
     *
     * One property: that property stringified.
     *
     * Multiple properties: PHP serialized representation.
     *
     * @return string
     */
    public function __toString() : string
    {
        $props = get_object_vars($this);
        switch (count($props)) {
            case 0:
                return '';
            case 1:
                $prop = reset($props);
                if ($prop === null || is_scalar($prop)
                    || (is_object($prop) && method_exists($prop, '__toString'))
                ) {
                    return '' . $prop;
                }
        }

        return serialize($props);
    }
}
