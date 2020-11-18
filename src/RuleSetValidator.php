<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleSetValidatorInterface;

use SimpleComplex\Validate\RuleSet\RuleSetValidatorTrait;
use SimpleComplex\Validate\RuleTraits\TypeRulesTrait;
use SimpleComplex\Validate\RuleTraits\EnumScalarNullTrait;
use SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait;

/**
 * Validator suited validation by rulet.
 *
 * Pattern rules of this validator are not type-checking by themselves;
 * however, the ruleset generator secures that a fitting type-checking rule
 * gets called before a pattern rule.
 * Not recommended for direct non-ruleset use unless the user makes sure to use
 * a fitting type-checking rule before a pattern rule.
 *
 * @see CheckedValidator
 *      Checked non-ruleset counterpart to this validator.
 *
 * @see TypeRulesTrait
 *      Type checking rules.
 * @see EnumScalarNullTrait
 * @see PatternRulesUncheckedTrait
 *      Pattern rules.
 *
 * @package SimpleComplex\Validate
 */
class RuleSetValidator extends AbstractValidator implements RuleSetValidatorInterface
{
    // Become a RuleSetValidatorInterface.
    use RuleSetValidatorTrait;


    /**
     * Public non-rule instance methods.
     *
     * @see RuleProviderIntegrity
     *
     * @var mixed[]
     */
    protected const NON_RULE_METHODS =
        AbstractValidator::NON_RULE_METHODS
        + RuleSetValidatorInterface::CHALLENGER_NON_RULE_METHODS
        + [
            // Deprecated.
            'challengeRecording' => null,
        ];
}
