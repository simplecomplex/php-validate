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

/** @noinspection PhpUnused */

/**
 * Type definitions used by ruleset generator to find a type-checking rule
 * matching a pattern rule.
 *
 * All integer constants must be unique.
 *
 * @see RuleSetGenerator::ensureTypeChecking()
 *
 * @package SimpleComplex\Validate
 */
class Type
{
    // Simple.------------------------------------------------------------------

    /**
     * @see ValidationRuleSet::$optional
     */
    public const UNDEFINED = 1;

    /**
     * @see TypeRulesTrait::null()
     * @see ValidationRuleSet::$nullable
     */
    public const NULL = 2;

    /**
     * @see TypeRulesTrait::boolean()
     */
    public const BOOLEAN = 4;

    /**
     * @see TypeRulesTrait::integer()
     */
    public const INTEGER = 8;

    /**
     * @see TypeRulesTrait::float()
     */
    public const FLOAT = 16;

    /**
     * @see TypeRulesTrait::string()
     */
    public const STRING = 32;

    /**
     * @see TypeRulesTrait::array()
     */
    public const ARRAY = 64;

    /**
     * @see TypeRulesTrait::object()
     * @see TypeRulesTrait::class()
     */
    public const OBJECT = 128;

    /**
     * @see TypeRulesTrait::resource()
     */
    public const RESOURCE = 65536;


    // Specials.----------------------------------------------------------------

    /**
     * Any type.
     * @see TypeRulesTrait::empty()
     * @see TypeRulesTrait::nonEmpty()
     */
    public const ANY = 2147483648;

    /**
     * Stringed number.
     * @see TypeRulesTrait::decimal()
     */
    public const DECIMAL = 1024;

    /**
     * String, number or stringable object.
     * @see TypeRulesTrait::stringable()
     */
    public const STRINGABLE = 2048;

    /**
     * Array or \Traversable object.
     * @see TypeRulesTrait::iterable()
     */
    public const ITERABLE = 4096;

    /**
     * Array or \Countable object.
     * @see TypeRulesTrait::countable()
     */
    public const COUNTABLE = 8192;


    // Composites.--------------------------------------------------------------

    /**
     * @see TypeRulesTrait::number()
     *
     * int|float: 8 + 16.
     */
    public const NUMBER = 24;

    /**
     * Integer or stringed integer.
     * @see TypeRulesTrait::digital()
     *
     * stringable + int: 2048 + 8.
     */
    public const DIGITAL = 2056;

    /**
     * Integer, float or stringed number.
     * @see TypeRulesTrait::numeric()
     *
     * stringable + int|float: 2048 + 8 + 16.
     */
    public const NUMERIC = 2072;

    /**
     * Boolean, integer or string.
     * @see TypeRulesTrait::equatable()
     *
     * bool|int|string: 4 + 8 + 32.
     */
    public const EQUATABLE = 44;

    /**
     * Null, boolean, integer or string.
     * @see TypeRulesTrait::equatable()
     *
     * null|bool|int|string: 2 + 4 + 8 + 32.
     */
    public const EQUATABLE_NULLABLE = 46;

    /**
     * @see TypeRulesTrait::scalar()
     *
     * bool|int|float|string: 4 + 8 + 16 + 32.
     */
    public const SCALAR = 60;

    /**
     * Scalar or null.
     * @see TypeRulesTrait::scalarNull()
     *
     * null|bool|int|float|string: 2 + 4 + 8 + 16 + 32.
     */
    public const SCALAR_NULLABLE = 62;

    /**
     * Stringable scalar.
     * @see TypeRulesTrait::stringableScalar()
     *
     * stringable + int|float|string: 2048 + 8 + 16 + 32.
     */
    public const STRINGABLE_SCALAR = 2104;

    /**
     * Stringable object.
     * @see TypeRulesTrait::stringableObject()
     *
     * stringable + object: 2048 + 128.
     */
    public const STRINGABLE_OBJECT = 2176;

    /**
     * String or stringable object.
     *
     * string + stringable + object: 32 + 2048 + 128.
     */
    public const STRING_STRINGABLE_OBJECT = 2208;

    /**
     * @see TypeRulesTrait::container()
     *
     * array|object: 64 + 128.
     */
    public const CONTAINER = 192;

    /**
     * @see TypeRulesTrait::loopable()
     *
     * iterable|object: 4096 + 128.
     */
    public const LOOPABLE = 4224;

    /**
     * @see TypeRulesTrait::sizeable()
     *
     * countable|object = 8192 + 128.
     */
    public const SIZEABLE = 8320;


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
