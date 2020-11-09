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
class RecursiveValidator
    extends AbstractValidator
    implements RecursiveValidatorInterface
{
    // Become a RecursiveValidatorInterface.
    use ChallengerTrait;


    /**
     * Public non-rule instance methods.
     *
     * @see RuleProviderIntegrity
     *
     * @var mixed[]
     */
    protected const NON_RULE_METHODS =
        AbstractValidator::NON_RULE_METHODS
        + RecursiveValidatorInterface::CHALLENGER_NON_RULE_METHODS
        + [
            // Deprecated.
            'challengeRecording' => null,
        ];
}
