<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Variants;

use SimpleComplex\Validate\Type;
use SimpleComplex\Validate\RuleSetValidator;

/**
 * Ruleset validator with enum() accepting bool|int|float|string.
 *
 * Ruleset generator will deny null bucket in allowed-values argument for enum.
 * @see Type::SCALAR
 *
 * @package SimpleComplex\Validate
 */
class EnumScalarRuleSetValidator extends RuleSetValidator
{
    /**
     * Tell ruleset generator (indirectly) that this validator only accepts
     * bool|int|float|string as allowed values.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator::enum()
     *
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const PATTERN_RULES = [
        'enum' => Type::SCALAR,
    ]
    + RuleSetValidator::PATTERN_RULES;
}
