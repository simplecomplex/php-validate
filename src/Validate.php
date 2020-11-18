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

/**
 * Validator supporting validation by ruleset as well as by rule method.
 *
 * Deprecated due to inferior performance when used for ruleset validation
 * and because infringes principle that checked and ruleset validators
 * shan't be the same species.
 *
 * @deprecated
 *      Use CheckedValidator and/or RuleSetValidator instead.
 * @see CheckedValidator
 * @see RuleSetValidator
 *      Better alternatives.
 *
 * @package SimpleComplex\Validate
 */
class Validate extends CheckedValidator implements RuleSetValidatorInterface
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
            'challenge' => null,
            'challengeRecording' => null,
        ];
}
