<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\TypeRulesInterface;
use SimpleComplex\Validate\Interfaces\PatternRulesInterface;

use SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait;
use SimpleComplex\Validate\RuleTraits\TypeRulesTrait;

/**
 * Intermediate class allowing Validate _not_ to extend
 * ValidateUnchecked.
 *
 * @see Validate
 * @see ValidateUnchecked
 *
 * @package SimpleComplex\Validate
 */
abstract class AbstractValidate
    extends AbstractRuleProvider
    implements TypeRulesInterface, PatternRulesInterface
{
    // Type-checking rules.
    use TypeRulesTrait;

    // Pattern rules.
    use PatternRulesUncheckedTrait;


    /**
     * Rules that explicitly promise to check the subject's type.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getTypeRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * @var int[]
     */
    const TYPE_RULES = TypeRulesInterface::MINIMAL_TYPE_RULES;

    /**
     * Rules that don't promise to check the subject's type.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getPatternRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * @var int[]
     */
    const PATTERN_RULES = PatternRulesInterface::MINIMAL_PATTERN_RULES;

    /**
     * Number of required parameters, by rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var int[]
     *
     * IDE: _is_ used.
     */
    const PARAMS_REQUIRED =
        TypeRulesInterface::TYPE_PARAMS_REQUIRED
        + PatternRulesInterface::PATTERN_PARAMS_REQUIRED;

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var int[]
     *
     * IDE: _is_ used.
     */
    const PARAMS_ALLOWED =
        TypeRulesInterface::TYPE_PARAMS_ALLOWED
        + PatternRulesInterface::PATTERN_PARAMS_ALLOWED;

    /**
     * New rule name by old rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var string[]
     *
     * IDE: _is_ used.
     */
    const RULES_RENAMED =
        TypeRulesInterface::TYPE_RULES_RENAMED
        + PatternRulesInterface::PATTERN_RULES_RENAMED;

    /**
     * Flags controlling behaviours of rules.
     *
     * @see TypeRulesTrait
     * @see PatternRulesTrait
     *
     * @var mixed[]
     */
    const RULE_FLAGS =
        TypeRulesInterface::TYPE_RULE_FLAGS
        + PatternRulesInterface::PATTERN_RULE_FLAGS;
}
