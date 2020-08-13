<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

use SimpleComplex\Validate\Type;

/**
 * Rules that promise to check the subject's type.
 *
 * Equivalent trait:
 * @see \SimpleComplex\Validate\RuleTraits\TypeRulesTrait
 *
 *
 * Required method parameter(s) is to be avoided
 * ---------------------------------------------
 * Type-checking rules should generally not require arguments, because
 * the ruleset generator must be able to use them freely.
 *
 * Illegal rule method names
 * -------------------------
 * optional, nullable, alternativeEnum, alternativeRuleSet, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * @package SimpleComplex\Validate
 */
interface TypeRulesInterface
{
    // API constants.-----------------------------------------------------------

    /**
     * Types of rules that explicitly promise to check the subject's type.
     *
     * @see RuleProviderInterface::getRule()
     * @see RuleProviderInterface::getTypeRuleType()
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then RuleSetGenerator makes a guess.
     * @see AbstractRuleProvider::PATTERN_RULES
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * Implementing class may do:
     * const TYPE_RULES = TypeRulesInterface::MINIMAL_TYPE_RULES;
     * Or use use PHP array union(+), like:
     * const TYPE_RULES = [
     *   'someRule' => Type::SOME_TYPE,
     * ] + TypeRulesInterface::MINIMAL_TYPE_RULES;
     *
     * @var int[]
     */
    public const MINIMAL_TYPE_RULES = [
        // Type indifferent, but type safe.
        'empty' => Type::ANY,
        'nonEmpty' => Type::ANY,

        // Scalar/null.-------------------------------------
        'null' => Type::NULL,
        'scalarNull' => Type::SCALAR_NULLABLE,
        'scalar' => Type::SCALAR,
        'equatableNull' => Type::EQUATABLE_NULLABLE,
        'equatable' => Type::EQUATABLE,
        'boolean' => Type::BOOLEAN,
        'bit' => Type::DIGITAL,
        'number' => Type::NUMBER,
        'integer' => Type::INTEGER,
        'float' => Type::FLOAT,

        // Number or stringed number.-----------------------
        'integerString' => Type::INTEGER_STRING,
        'floatString' => Type::FLOAT_STRING,
        'numeric' => Type::NUMERIC,
        'digital' => Type::DIGITAL,
        'decimal' => Type::DECIMAL,

        // String/stringable.-------------------------------
        'string' => Type::STRING,
        'stringableScalar' => Type::STRINGABLE_SCALAR,
        'stringable' => Type::STRINGABLE,
        'stringStringable' => Type::STRING_STRINGABLE,
        'anyStringable' => Type::ANY_STRINGABLE,

        // Container.---------------------------------------
        'object' => Type::OBJECT,
        'class' => Type::OBJECT,
        'stdClass' => Type::STDCLASS,
        'extClass' => Type::EXTCLASS,
        // Anonymous class is an extending class (object not \stdClass)
        // with a bad class name.
        'anonymousClass' => Type::EXTCLASS,
        'array' => Type::ARRAY,
        'container' => Type::CONTAINER,
        'iterable' => Type::ITERABLE,
        'loopable' => Type::LOOPABLE,
        'countable' => Type::COUNTABLE,
        'sizeable' => Type::SIZEABLE,

        // Pattern-like container rules that have to check type to work.
        'minSize' => Type::SIZEABLE,
        'maxSize' => Type::SIZEABLE,
        'exactSize' => Type::SIZEABLE,
        'indexedIterable' => Type::ITERABLE,
        'keyedIterable' => Type::ITERABLE,
        'indexedLoopable' => Type::LOOPABLE,
        'keyedLoopable' => Type::LOOPABLE,
        'indexedArray' => Type::LOOPABLE,
        'keyedArray' => Type::LOOPABLE,

        // Odd types.---------------------------------------
        'resource' => Type::RESOURCE,
    ];

    /**
     * Number of required parameters, by rule name.
     *
     * @var int[]
     */
    public const TYPE_PARAMS_REQUIRED = [
        'class' => 1,
        'minSize' => 1,
        'maxSize' => 1,
        'exactSize' => 1,
    ];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule name.
     *
     * @var int[]
     */
    public const TYPE_PARAMS_ALLOWED = [
    ];

    /**
     * New rule name by old rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var string[]
     */
    public const TYPE_RULES_RENAMED = [];


    // Rule constants.----------------------------------------------------------

    /**
     * Flags controlling behaviours of rules.
     *
     * @var mixed[]
     */
    public const TYPE_RULE_FLAGS = [
        /**
         * @see \SimpleComplex\Validate\RuleTraits\TypeRulesTrait::numeric()
         * @see \SimpleComplex\Validate\RuleTraits\TypeRulesTrait::digital()
         * @see \SimpleComplex\Validate\RuleTraits\TypeRulesTrait::decimal()
         */
        'STRINGED_NEGATIVE_ZERO' => false,
    ];


    // Type indifferent, but type safe.-----------------------------------------

    public function empty($subject) : bool;

    public function nonEmpty($subject) : bool;


    // Scalar/null.-------------------------------------------------------------

    public function null($subject) : bool;

    public function scalarNull($subject) : bool;

    public function scalar($subject) : bool;

    public function equatableNull($subject) : bool;

    public function equatable($subject) : bool;

    public function boolean($subject) : bool;

    public function bit($subject) : bool;

    public function number($subject) : bool;

    public function integer($subject) : bool;

    public function float($subject) : bool;


    // Number or stringed number.----------------------------------------------

    public function integerString($subject) : bool;

    public function floatString($subject) : bool;

    public function numeric($subject) : bool;

    public function digital($subject) : bool;

    public function decimal($subject) : bool;


    // String/stringable.-------------------------------------------------------

    public function string($subject) : bool;

    public function stringableScalar($subject) : bool;

    public function stringable($subject) : bool;

    public function stringStringable($subject) : bool;

    public function anyStringable($subject) : bool;


    // Container.---------------------------------------------------------------

    public function object($subject) : bool;

    public function class($subject, string $className) : bool;

    public function stdClass($subject) : bool;

    public function extClass($subject) : bool;

    public function anonymousClass($subject) : bool;

    public function array($subject) : bool;

    public function container($subject) : bool;

    public function iterable($subject) : bool;

    public function loopable($subject) : bool;

    public function countable($subject) : bool;

    public function sizeable($subject) : bool;


    // Pattern-like container rules that have to check type to work.------------

    public function minSize($subject, int $min) : bool;

    public function maxSize($subject, int $min) : bool;

    public function exactSize($subject, int $min) : bool;

    public function indexedIterable($subject) : bool;

    public function keyedIterable($subject) : bool;

    public function indexedLoopable($subject) : bool;

    public function keyedLoopable($subject) : bool;

    public function indexedArray($subject) : bool;

    public function keyedArray($subject) : bool;


    // Odd types.---------------------------------------------------------------

    public function resource($subject) : bool;
}
