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
use SimpleComplex\Validate\Validator;
use SimpleComplex\Validate\RuleTraits\EnumScalarTrait;

/**
 * Checked validator with enum() accepting bool|int|string.
 *
 * @see Validator
 *
 * Unchecked counterpart:
 * @see EnumScalarUncheckedValidator
 *
 * @package SimpleComplex\Validate
 */
class EnumScalarValidator extends Validator
{
    use EnumScalarTrait;

    /**
     * Tell ruleset generator (indirectly) that this validator only accepts
     * bool|int|float|string values.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator::enum()
     *
     * In an all type-checking validator all rules are type-rules.
     * @see Validator::TYPE_RULES
     * @see Validator::PATTERN_RULES
     *
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const TYPE_RULES = [
        'enum' => Type::SCALAR,
    ]
    + Validator::TYPE_RULES;
}
