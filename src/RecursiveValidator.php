<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

/**
 * @todo: obsolete, make AbstractValidator concrete and rename that to RecursiveValidator.
 *
 * High performance validator targeted ruleset validation.
 *
 * Also usable 'manually', but then user _must_ secure that the subject
 * gets checked by a type-checking rule before a pattern rule.
 *
 * BEWARE: Pattern rules of this validator do _not_ check subject's type.
 *      Without a preceding type-check (failing on unexpected subject type)
 *      these pattern rules are unreliable, and may produce fatal error
 *      (attempt to stringify object without __toString() method).
 *
 * Type checking rules:
 * @see TypeRulesTrait
 * Pattern rules:
 * @see EnumScalarNullTrait
 * @see PatternRulesUncheckedTrait
 *
 * @package SimpleComplex\Validate
 */
class RecursiveValidator extends AbstractValidator
{
    /**
     * Extends AbstractValidate to allow the all type-checking Validator
     * @see Validator
     * class not to extend this class.
     *
     * Contains all rules of these traits:
     * @see TypeRulesTrait
     * @see EnumScalarNullTrait
     * @see PatternRulesUncheckedTrait
     */
}
