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
 * BEWARE: The exact values may vary across versions of this library.
 * Use the constants, not their literal values.
 *
 * @see RuleSetGenerator::ensureTypeChecking()
 *
 * @package SimpleComplex\Validate
 */
class Type
{
    // Simple.------------------------------------------------------------------

    /**
     * Any type.
     * @see TypeRulesTrait::empty()
     * @see TypeRulesTrait::nonEmpty()
     */
    public const ANY = 2147483648;

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
     * \stdClass.
     * @see TypeRulesTrait::stdClass()
     */
    public const STDCLASS = 128;

    /**
     * Object not \stdClass; extending class.
     * @see TypeRulesTrait::extClass()
     */
    public const EXTCLASS = 256;

    /**
     * @see TypeRulesTrait::resource()
     */
    public const RESOURCE = 65536;


    // Modifiers.---------------------------------------------------------------

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
     * INTEGER + FLOAT.
     */
    public const NUMBER = 8 + 16;

    /**
     * Integer or stringed integer.
     * @see TypeRulesTrait::digital()
     *
     * STRINGABLE + INTEGER.
     */
    public const DIGITAL = 2048 + 8;

    /**
     * Integer, float or stringed number.
     * @see TypeRulesTrait::numeric()
     *
     * STRINGABLE + INTEGER + FLOAT.
     */
    public const NUMERIC = 2048 + 8 + 16;

    /**
     * Boolean, integer or string.
     * @see TypeRulesTrait::equatable()
     *
     * BOOLEAN + INTEGER + STRING.
     */
    public const EQUATABLE = 4 + 8 + 32;

    /**
     * Null, boolean, integer or string.
     * @see TypeRulesTrait::equatable()
     *
     * NULL + BOOLEAN + INTEGER + STRING.
     */
    public const EQUATABLE_NULLABLE = 2 + 4 + 8 + 32;

    /**
     * @see TypeRulesTrait::scalar()
     *
     * BOOLEAN + INTEGER + FLOAT + STRING.
     */
    public const SCALAR = 4 + 8 + 16 + 32;

    /**
     * Scalar or null.
     * @see TypeRulesTrait::scalarNull()
     *
     * NULL + BOOLEAN + INTEGER + FLOAT + STRING.
     */
    public const SCALAR_NULLABLE = 2 + 4 + 8 + 16 + 32;

    /**
     * Stringable scalar.
     * @see TypeRulesTrait::stringableScalar()
     *
     * STRINGABLE + INTEGER + FLOAT + STRING.
     */
    public const STRINGABLE_SCALAR = 2048 + 8 + 16 + 32;

    /**
     * Stringable object.
     * @see TypeRulesTrait::stringableObject()
     *
     * STRINGABLE + EXTCLASS.
     */
    public const STRINGABLE_OBJECT = 2048 + 256;

    /**
     * String or stringable object.
     *
     * STRING + STRINGABLE + EXTCLASS.
     */
    public const STRING_STRINGABLE_OBJECT = 32 + 2048 + 256;

    /**
     * \stdClass or extending class.
     * @see TypeRulesTrait::object()
     * STDCLASS + EXTCLASS.
     */
    public const OBJECT = 128 + 256;

    /**
     * Array or object.
     * @see TypeRulesTrait::container()
     * ARRAY + STDCLASS + EXTCLASS.
     */
    public const CONTAINER = 64 + 128 + 256;

    /**
     * Iterable or \stdClass.
     * @see TypeRulesTrait::loopable()
     * ITERABLE + STDCLASS.
     */
    public const LOOPABLE = 4096 + 128;

    /**
     * \Countable, Iterable or \stdClass.
     * @see TypeRulesTrait::sizeable()
     * COUNTABLE + ITERABLE + STDCLASS.
     */
    public const SIZEABLE = 8192 + 4096 + 128;


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
