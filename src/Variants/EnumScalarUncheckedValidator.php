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
use SimpleComplex\Validate\UncheckedValidator;
use SimpleComplex\Validate\RuleTraits\EnumScalarTrait;

/**
 * Unchecked validator with enum() accepting bool|int|float|string.
 *
 * @see UncheckedValidator
 *
 * Checked counterpart:
 * @see EnumScalarValidator
 *
 * @package SimpleComplex\Validate
 */
class EnumScalarUncheckedValidator extends UncheckedValidator
{
    use EnumScalarTrait;

    /**
     * Tell ruleset generator (indirectly) that this validator only accepts
     * bool|int|float|string values.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator::enum()
     *
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const PATTERN_RULES = [
        'enum' => Type::SCALAR,
    ]
    + UncheckedValidator::PATTERN_RULES;
}
