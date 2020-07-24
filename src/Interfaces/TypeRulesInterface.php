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
 * Rules that promise to check subject's type.
 *
 * Equivalent trait:
 * @see \SimpleComplex\Validate\RuleTraits\TypeRulesTrait
 *
 * Required method parameter(s) is illegal
 * --------------------------------------
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
     * Rules that explicitly promise to check the subject's type.
     *
     * @see RuleProviderInterface::getRule()
     * @see RuleProviderInterface::getTypeRuleType()
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then RuleSetGenerator makes a guess.
     * @see AbstractRuleProvider::PATTERN_RULES
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * Keys are methods names, values may be anything.
     * Allows a child class to extend parent's list by doing
     * const SOME_CONSTANT = [
     *   'someMethod' => null,
     * ] + ParentClass::SOME_CONSTANT;
     *
     * @var int[]
     */
    const MINIMAL_TYPE_RULES = [
        'null' => Type::NULL,
        'scalarNull' => Type::SCALAR_NULLABLE,
        'scalar' => Type::SCALAR,
        'equatable' => Type::EQUATABLE,
        'boolean' => Type::BOOLEAN,
        'bit' => Type::DIGITAL,
        'number' => Type::NUMBER,
        'integer' => Type::INTEGER,
        'float' => Type::FLOAT,
        'string' => Type::STRING,
        'stringableScalar' => Type::STRINGABLE_SCALAR,
        'stringableObject' => Type::STRINGABLE_OBJECT,
        'stringStringableObject' => Type::STRING_STRINGABLE_OBJECT,
        'stringable' => Type::STRINGABLE,
        'resource' => Type::RESOURCE,
        'numeric' => Type::NUMERIC,
        'digital' => Type::DIGITAL,
        'decimal' => Type::DECIMAL,
        'object' => Type::OBJECT,
        'class' => Type::OBJECT,
        'array' => Type::ARRAY,
        'container' => Type::CONTAINER,
        'iterable' => Type::ITERABLE,
        'loopable' => Type::LOOPABLE,
        'indexedIterable' => Type::ITERABLE,
        'keyedIterable' => Type::ITERABLE,
        'indexedLoopable' => Type::LOOPABLE,
        'keyedLoopable' => Type::LOOPABLE,
        'indexedArray' => Type::LOOPABLE,
        'keyedArray' => Type::LOOPABLE,
    ];

    /**
     * Number of required parameters, by rule name.
     *
     * @var int[]
     */
    const TYPE_PARAMS_REQUIRED = [
        'class' => 1,
    ];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule name.
     *
     * @var int[]
     */
    const TYPE_PARAMS_ALLOWED = [
    ];

    /**
     * New rule name by old rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var string[]
     */
    const TYPE_RULES_RENAMED = [];


    // Rule constants.----------------------------------------------------------

    /**
     * Flags controlling behaviours of rules.
     *
     * @var mixed[]
     */
    const TYPE_RULE_FLAGS = [
        /**
         * @see \SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait::numeric()
         */
        'DECIMAL_NEGATIVE_ZERO' => false,
    ];


    // Scalar/null.-------------------------------------------------------------

    public function null($subject) : bool;

    public function scalarNull($subject) : bool;

    public function scalar($subject) : bool;

    public function equatable($subject) : bool;

    public function boolean($subject) : bool;

    public function bit($subject) : bool;

    public function number($subject);

    public function integer($subject) : bool;

    public function float($subject) : bool;


    // String/stringable.-------------------------------------------------------

    public function string($subject) : bool;

    public function stringableScalar($subject) : bool;

    public function stringableObject($subject) : bool;

    public function stringStringableObject($subject) : bool;

    public function stringable($subject);


    // Odd types.---------------------------------------------------------------

    public function resource($subject) : bool;


    // Numbers or stringed numbers.---------------------------------------------

    public function numeric($subject);

    public function digital($subject) : bool;

    public function decimal($subject) : bool;

    public function object($subject) : bool;

    public function class($subject, string $className) : bool;

    public function array($subject) : bool;


    // Containers.--------------------------------------------------------------

    public function container($subject);

    public function iterable($subject);

    public function loopable($subject);

    public function indexedIterable($subject);

    public function keyedIterable($subject);

    public function indexedLoopable($subject);

    public function keyedLoopable($subject);

    public function indexedArray($subject) : bool;

    public function keyedArray($subject) : bool;
}
