<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Exception\InvalidRuleException;


/**
 * Type definitions used by ruleset generator to find a type-checking rule
 * matching a pattern rule.
 *
 * @see RuleSetGenerator::ensureTypeChecking()
 *
 * @package SimpleComplex\Validate
 */
class Type
{
    // Simple.------------------------------------------------------------------

    const UNDEFINED = 1;

    const NULL = 2;

    const BOOLEAN = 4;

    const INTEGER = 8;

    const FLOAT = 16;

    const STRING = 32;

    const ARRAY = 64;

    const OBJECT = 128;

    const RESOURCE = 65536;


    // Specials.----------------------------------------------------------------

    /**
     * Stringed number.
     */
    const DECIMAL = 1024;

    /**
     * Array or \Traversable object.
     */
    const ITERABLE = 2048;


    // Composites.--------------------------------------------------------------

    /**
     * int|float.
     * 8 + 16
     */
    const NUMBER = 24;

    /**
     * Integer or stringed integer.
     *
     * int|string.
     * 8 + 32
     */
    const DIGITAL = 40;

    /**
     * Integer, float or stringed number.
     *
     * int|float|string.
     * 8 + 16 + 32
     */
    const NUMERIC = 56;

    /**
     * null|bool|int|string.
     * 2 + 4 + 8 + 32
     */
    const EQUATABLE = 62;

    /**
     * bool|int|float|string.
     * 4 + 8 + 16 + 32
     */
    const SCALAR = 60;

    /**
     * null|bool|int|float|string.
     * 2 + 4 + 8 + 16 + 32
     */
    const SCALAR_NULLABLE = 62;

    /**
     * int|float|string|object.
     * 8 + 16 + 32 + 128
     */
    const STRINGABLE = 184;

    /**
     * array|object.
     * 64 + 128.
     */
    const CONTAINER = 192;

    /**
     * iterable|object.
     * 2048 + 128.
     */
    const LOOPABLE = 2196;


    /**
     * @param int $type
     *
     * @return string
     *
     * @throws InvalidRuleException
     *      Arg $type value not supported.
     */
    public static function typeName(int $type) : string
    {
        // Don't propagate weird unlikely exception types.
        try {
            $oRflctn = new \ReflectionClass(get_called_class());
            $name = array_search($type, $oRflctn->getConstants(), true);
            if ($name) {
                return $name;
            }
            throw new InvalidRuleException('Arg $type value[' . $type . '] is not supported');
        }
        catch (\ReflectionException $xcptn) {
            throw new InvalidRuleException('See previous.', 0, $xcptn);
        }
    }
}
