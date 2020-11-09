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

/**
 * Checked validator supporting recursive ruleset validation.
 *
 * Deprecated due to inferior performance when used for ruleset validation
 * and because infringes principle that checked and recursive validators
 * shan't be the same species.
 *
 * @deprecated
 *      Use CheckedValidator and/or RecursiveValidator instead.
 * @see CheckedValidator
 * @see RecursiveValidator
 *      Better alternatives.
 *
 * @package SimpleComplex\Validate
 */
class Validate extends CheckedValidator implements RecursiveValidatorInterface
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
