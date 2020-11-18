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
 * Validator suited validation by method.
 *
 * All rule methods of this validator are in themselves type-checking.
 *
 * @see RuleSetValidator
 *      Unchecked ruleset counterpart of this validator.
 *
 * @see TypeRulesTrait
 * @see EnumScalarNullTrait
 * @see PatternRulesCheckedTrait
 *      All rules type-checking, even the pattern rules.
 *
 * @package SimpleComplex\Validate
 */
class CheckedValidator extends AbstractValidator implements CheckedValidatorInterface
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
        AbstractValidator::TYPE_RULES
        + AbstractValidator::PATTERN_RULES;

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
