<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RecursiveValidatorInterface;
use SimpleComplex\Validate\Interfaces\TypeRulesInterface;
use SimpleComplex\Validate\Interfaces\PatternRulesInterface;

use SimpleComplex\Validate\RuleSet\ChallengerTrait;
use SimpleComplex\Validate\RuleTraits\TypeRulesTrait;
use SimpleComplex\Validate\RuleTraits\EnumScalarNullTrait;
use SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait;

/**
 * High performance validator suited ruleset validation.
 *
 * Also usable for direct non-ruleset use, but then user _must_ secure that the
 * subject gets checked by a type-checking rule before a pattern rule.
 *
 * BEWARE: Pattern rules of this validator do _not_ check subject's type.
 *      Without a preceding type-check (failing on unexpected subject type)
 *      these pattern rules are unreliable, and may produce fatal error
 *      (like attempt to stringify object without __toString() method).
 *
 * Type checking rules:
 * @see TypeRulesTrait
 * Pattern rules:
 * @see EnumScalarNullTrait
 * @see PatternRulesUncheckedTrait
 *
 * @package SimpleComplex\Validate
 */
class UncheckedValidator
    extends AbstractRuleProvider
    implements RecursiveValidatorInterface, TypeRulesInterface, PatternRulesInterface
{
    // Become a RecursiveValidatorInterface.
    use ChallengerTrait;

    // Type-checking rules.
    use TypeRulesTrait;

    // Pattern rules.
    use EnumScalarNullTrait;
    use PatternRulesUncheckedTrait;


    /**
     * Public non-rule instance methods.
     *
     * @see RuleProviderIntegrity
     *
     * @var mixed[]
     */
    protected const NON_RULE_METHODS =
        AbstractRuleProvider::NON_RULE_METHODS
        + RecursiveValidatorInterface::CHALLENGER_NON_RULE_METHODS
        + [
            // Deprecated.
            'challengeRecording' => null,
        ];

    /**
     * Types of rules that explicitly promise to check the subject's type.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getTypeRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * @var int[]
     */
    protected const TYPE_RULES = TypeRulesInterface::MINIMAL_TYPE_RULES;

    /**
     * Types of rules that don't promise to check the subject's type.
     *
     * @see AbstractRuleProvider::getRuleNames()
     * @see AbstractRuleProvider::getRule()
     * @see AbstractRuleProvider::getPatternRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * @var int[]
     */
    protected const PATTERN_RULES = PatternRulesInterface::MINIMAL_PATTERN_RULES;

    /**
     * Number of required parameters, by rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var int[]
     *
     * IDE: _is_ used, by AbstractRuleProvider::getRule().
     */
    protected const PARAMS_REQUIRED =
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
     * IDE: _is_ used, by AbstractRuleProvider::getRule().
     */
    protected const PARAMS_ALLOWED =
        TypeRulesInterface::TYPE_PARAMS_ALLOWED
        + PatternRulesInterface::PATTERN_PARAMS_ALLOWED;

    /**
     * New rule name by old rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var string[]
     *
     * IDE: _is_ used, by AbstractRuleProvider::getRule().
     */
    protected const RULES_RENAMED =
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
    protected const RULE_FLAGS =
        TypeRulesInterface::TYPE_RULE_FLAGS
        + PatternRulesInterface::PATTERN_RULE_FLAGS;
}
