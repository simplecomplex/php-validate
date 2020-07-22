<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

/**
 * Describes required properties of a class - a 'rule provider' - that can
 * provide validation rules for a ValidateAgainstRuleSet instance.
 *
 * Illegal rule names
 * ------------------
 * optional, nullable, alternativeEnum, alternativeRuleSet, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * Rule methods invalid arg checks
 * -------------------------------
 * Rules that take more arguments than the $subject to validate
 * must check those arguments for type/emptyness and throw exception
 * on such error.
 *
 * @package SimpleComplex\Validate
 */
interface RuleProviderInterface
{
    // Recursive validation facilitators.---------------------------------------

    /**
     * Lists validation rule methods.
     *
     * @return string[]
     *
     * @see AbstractRuleProvider::getRuleMethods()
     */
    public function getRuleMethods() : array;

    /**
     * Lists rule methods that explicitly promise to check the subject's type.
     *
     * @return string[]
     *
     * @see AbstractRuleProvider::getTypeRules()
     */
    public function getTypeRules() : array;

    /**
     * Methods that don't do type-checking, and what type they implicitly
     * expects.
     *
     * @return int[]
     *
     * @see AbstractRuleProvider::getTypeInference()
     */
    public function getTypeInference() : array;

    /**
     * Lists rules renamed; current rule name by old rule name.
     *
     * @return string[]
     *
     * @see AbstractRuleProvider::getRulesRenamed()
     */
    public function getRulesRenamed() : array;

    /**
     * Two lists of numbers of required/allowed arguments.
     *
     * required: Number of required parameters, by rule method name.
     *
     * allowed: Number of allowed parameters - if none required
     *      or if allows more than required - by rule method name.
     *
     * @return int[][] {
     *      @var int[] $required
     *      @var int[] $allowed
     * }
     *
     * @see AbstractRuleProvider::getParameterSpecs()
     */
    public function getParameterSpecs() : array;


    // Validation rule methods.-------------------------------------------------

    /**
     * Subject is falsy or array|object is empty.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * Method expected by ruleset generator.
     * @see RuleSetGenerator::ruleByKey()
     * @see RuleSetGenerator::ruleByValue()
     *
     * @param mixed $subject
     *
     * @return bool
     *
     * @see TypeRulesTrait::empty()
     */
    public function empty($subject) : bool;

    /**
     * Subject is not falsy or array|object is non-empty.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * Method expected by ruleset generator.
     * @see RuleSetGenerator::ruleByKey()
     * @see RuleSetGenerator::ruleByValue()
     *
     * @param mixed $subject
     *
     * @return bool
     *
     * @see TypeRulesTrait::nonEmpty()
     */
    public function nonEmpty($subject) : bool;

    /**
     * Is null.
     *
     * Method expected by recursive validator.
     * @see ValidateAgainstRuleSet::internalChallenge()
     *
     * @param mixed $subject
     *
     * @return bool
     *
     * @see TypeRulesTrait::null()
     */
    public function null($subject) : bool;

    /**
     * Checks for equality against a list of scalar|null values.
     *
     * Implementation is free to forbid (fail on) float.
     *
     * Method expected by recursive validator, for alternativeEnum.
     * @see ValidateAgainstRuleSet::internalChallenge()
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar
     *          1: null
     *          3: other scalar
     *      ]
     *
     * @return bool
     *
     * @see PatternRulesUncheckedTrait::enum()
     */
    public function enum($subject, array $allowedValues) : bool;

    /**
     * Array or Traversable object, or non-Traversable non-ArrayAccess object.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * Method expected by recursive validator, for tableElements, listItems.
     * @see ValidateAgainstRuleSet::internalChallenge()
     *
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     *
     * @see TypeRulesTrait::loopable()
     */
    public function loopable($subject);
}
