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
use SimpleComplex\Validate\RuleTraits\EnumEquatableNullCheckedTrait;

/**
 * Checked validator with enum() accepting bool|int|string|null.
 *
 * @see Validator
 *
 * Unchecked counterpart:
 * @see EnumEquatableNullUncheckedValidator
 *
 * @package SimpleComplex\Validate
 */
class EnumEquatableNullValidator extends Validator
{
    use EnumEquatableNullCheckedTrait;

    /**
     * Tell ruleset generator (indirectly) that this validator only accepts
     * bool|int|string values.
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
        'enum' => Type::EQUATABLE_NULL,
    ]
    + Validator::TYPE_RULES;
}
