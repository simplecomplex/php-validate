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
     * @see AbstractRuleProvider::getRuleNames()
     */
    public function getRuleNames() : array;

    /**
     * Get object describing the rule.
     *
     * @param string $name
     *
     * @return Rule|null
     *      Null: nonexistent rule.
     *
     * @see AbstractRuleProvider::getRule()
     */
    public function getRule(string $name) : ?Rule;

    /**
     * Get type affiliation of a type-checking rule.
     *
     * @see TypeRulesInterface::MINIMAL_TYPE_RULES
     * @see Type
     *
     * @param string $name
     *
     * @return int|null
     *
     * @see AbstractRuleProvider::getTypeRuleType()
     */
    public function getTypeRuleType(string $name) : ?int;

    /**
     * Get type affiliation of a pattern rule.
     *
     * @see PatternRulesInterface::MINIMAL_PATTERN_RULES
     * @see Type
     *
     * @param string $name
     *
     * @return int|null
     *
     * @see AbstractRuleProvider::getPatternRuleType()
     */
    public function getPatternRuleType(string $name) : ?int;

    /**
     * Get type rule fitting as type-checker for a pattern rule.
     *
     * For ruleset generator.
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @param int|null $patternType
     *      Required if no $patternRuleName,
     *      ignored if $patternRuleName.
     * @param string|null $patternRuleName
     *
     * @return string|null
     *      Null: no such pattern rule, or type rule type, found.
     *
     * @throws \InvalidArgumentException
     *      Both arguments falsy.
     *
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     */
    public function patternRuleToTypeRule(int $patternType = null, string $patternRuleName = null) : ?string;


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
