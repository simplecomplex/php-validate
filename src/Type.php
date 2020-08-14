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
use SimpleComplex\Validate\Exception\InvalidArgumentException;

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
     *
     * Built-in and userland classes actually don't extend \stdClass* or another
     * (PHP missing) Object base class.
     * But the point is that a non-\stdClass class is unknown territory except
     * for qualities revealed through interface compliance.
     * Is it traversable? Maybe. All we know is that \stdClass and \Traversable
     * definitely are.
     *
     * *At least not normally, because the language doesn't enforce that,
     * and there's no benefit by doing it.
     *
     * @see TypeRulesTrait::extClass()
     */
    public const EXTCLASS = 256;

    /**
     * @see TypeRulesTrait::resource()
     */
    public const RESOURCE = 65536;


    // Stringed scalars.--------------------------------------------------------

    /**
     * Stringed integer.
     */
    public const INTEGER_STRING = 1024;

    /**
     * Stringed float.
     */
    public const FLOAT_STRING = 2048;


    // Interfaces.--------------------------------------------------------------

    /**
     * Array or \Countable object.
     * @see TypeRulesTrait::countable()
     */
    public const COUNTABLE = 8192;


    // @todo: TRAVERSABLE, and ITERABLE = ARRAY + TRAVERSABLE.

    /**
     * Array or \Traversable object.
     * @see TypeRulesTrait::iterable()
     */
    public const ITERABLE = 16384;

    /**
     * Stringable object.
     * @see TypeRulesTrait::stringable()
     */
    public const STRINGABLE = 32768;


    // Composites.--------------------------------------------------------------

    /**
     * @see TypeRulesTrait::bit()
     *
     * BOOLEAN + INTEGER.
     */
    public const BIT = 4 + 8;

    /**
     * @see TypeRulesTrait::number()
     *
     * INTEGER + FLOAT.
     */
    public const NUMBER = 8 + 16;

    /**
     * Integer, float or stringed number.
     * @see TypeRulesTrait::numeric()

     * INTEGER + FLOAT + INTEGER_STRING + FLOAT_STRING
     */
    public const NUMERIC = 8 + 16 + 1024 + 2048;

    /**
     * Integer or stringed integer.
     * @see TypeRulesTrait::digital()
     *
     * INTEGER + INTEGER_STRING
     */
    public const DIGITAL = 8 + 1024;

    /**
     * Stringed number.
     * @see TypeRulesTrait::decimal()
     *
     * INTEGER_STRING + FLOAT_STRING
     */
    public const DECIMAL = 1024 + 2048;

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
    public const EQUATABLE_NULL = 2 + 4 + 8 + 32;

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
    public const SCALAR_NULL = 2 + 4 + 8 + 16 + 32;

    /**
     * Stringable scalar.
     * @see TypeRulesTrait::stringableScalar()
     *
     * INTEGER + FLOAT + STRING
     */
    public const STRINGABLE_SCALAR = 8 + 16 + 32;

    /**
     * String or stringable object.
     *
     * STRING + STRINGABLE
     */
    public const STRING_STRINGABLE = 32 + 32768;

    /**
     * String, number or stringable object.
     * @see TypeRulesTrait::anyStringable()
     *
     * INTEGER + FLOAT + STRING + STRINGABLE
     */
    public const ANY_STRINGABLE = 8 + 16 + 32 + 32768;

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
    public const LOOPABLE = 16384 + 128;

    /**
     * \Countable, Iterable or \stdClass.
     * @see TypeRulesTrait::sizeable()
     * COUNTABLE + ITERABLE + STDCLASS.
     */
    public const SIZEABLE = 8192 + 16384 + 128;


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

    /**
     * Get pipe-separated list of type aliases covered by a type.
     *
     * @param int $type
     *
     * @return string
     *
     * @throws InvalidArgumentException
     *      Arg $type not supported.
     */
    public static function typeMessage(int $type) : string
    {
        switch ($type) {
            case static::ANY:
                return 'any';
            case static::UNDEFINED:
                return 'undefined';
            case static::NULL:
                return 'null';
            case static::BOOLEAN:
                return 'boolean';
            case static::INTEGER:
                return 'integer';
            case static::FLOAT:
                return 'float';
            case static::STRING:
                return 'string';
            case static::ARRAY:
                return 'array';
            case static::STDCLASS:
                return '\stdClass';
            case static::EXTCLASS:
                return 'object-not-\stdClass';
            case static::RESOURCE:
                return 'resource';
            case static::INTEGER_STRING:
                return 'stringed-integer';
            case static::FLOAT_STRING:
                return 'stringed-float';
            case static::COUNTABLE:
                return 'array|\Countable';
            case static::ITERABLE:
                return 'array|\Traversable';
            case static::STRINGABLE:
                return 'stringable-object';
            case static::BIT:
                return 'false|true|0|1';
            case static::NUMBER:
                return 'integer|float';
            case static::NUMERIC:
                return 'integer|float|stringed-number';
            case static::DIGITAL:
                return 'integer|stringed-integer';
            case static::DECIMAL:
                return 'stringed-number';
            case static::EQUATABLE:
                return 'boolean|integer|string';
            case static::EQUATABLE_NULL:
                return 'boolean|integer|string|null';
            case static::SCALAR:
                return 'boolean|integer|float|string';
            case static::SCALAR_NULL:
                return 'boolean|integer|float|string|null';
            case static::STRINGABLE_SCALAR:
                return 'integer|float|string';
            case static::STRING_STRINGABLE:
                return 'string|stringable-object';
            case static::ANY_STRINGABLE:
                return 'string|integer|float|stringable-object';
            case static::OBJECT:
                return 'object';
            case static::CONTAINER:
                return 'array|object';
            case static::LOOPABLE:
                return 'array|\Traversable|\stdClass';
            case static::SIZEABLE:
                return 'array|\Countable|\Traversable|\stdClass';
        }
        throw new InvalidArgumentException('Arg $type value[' . $type . '] is not a supported type.');
    }
}
