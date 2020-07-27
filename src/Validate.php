<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\CheckedRuleProviderInterface;

use SimpleComplex\Validate\RuleTraits\PatternRulesCheckedTrait;

/**
 * Validator targeted 'manual' (non-ruleset) use.
 *
 * All rules of this class are type-checking.
 *
 * The safe but slow choice.
 * Slow because subject's type will be checked repeatedly, by every rule used.
 *
 * @package SimpleComplex\Validate
 */
class Validate extends AbstractValidate implements CheckedRuleProviderInterface
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
        ValidateUnchecked::TYPE_RULES
        + ValidateUnchecked::PATTERN_RULES;

    /**
     * No need for type inference at all.
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
