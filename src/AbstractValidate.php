<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\ChallengerInterface;
use SimpleComplex\Validate\Interfaces\TypeRulesInterface;
use SimpleComplex\Validate\Interfaces\PatternRulesInterface;

use SimpleComplex\Validate\RuleTraits\TypeRulesTrait;
use SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait;

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
    implements ChallengerInterface, TypeRulesInterface, PatternRulesInterface
{
    // Become a ChallengerInterface.
    use ChallengerTrait;

    // Type-checking rules.
    use TypeRulesTrait;

    // Pattern rules.
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
        + ChallengerInterface::CHALLENGER_NON_RULE_METHODS
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
