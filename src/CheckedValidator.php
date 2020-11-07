<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\CheckedValidatorInterface;

use SimpleComplex\Validate\RuleTraits\PatternRulesCheckedTrait;

/**
 * Validator suitable for non-ruleset use.
 *
 * All rules of this class are type-checking.
 *
 * The safe choice. But slow if checking a subject against more rules (methods)
 * because then subject's type will be checked repeatedly, by every rule used.
 *
 * @package SimpleComplex\Validate
 */
class CheckedValidator extends UncheckedValidator implements CheckedValidatorInterface
{
    // Pattern rules that are type-checking.
    use PatternRulesCheckedTrait;

    /**
     * All methods are type-checking.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getTypeRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * IDE: _is_ used.
     */
    protected const TYPE_RULES =
        UncheckedValidator::TYPE_RULES
        + UncheckedValidator::PATTERN_RULES;

    /**
     * No methods are not type-checking.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getPatternRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * IDE: _is_ used.
     */
    protected const PATTERN_RULES = [];
}
