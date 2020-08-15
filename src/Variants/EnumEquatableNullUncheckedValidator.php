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

/**
 * Unchecked validator with enum() accepting bool|int|string|null.
 *
 * Ruleset generator will deny float bucket in allowed-values argument for enum.
 * @see Type::EQUATABLE_NULL
 *
 * @package SimpleComplex\Validate
 */
class EnumEquatableNullUncheckedValidator extends UncheckedValidator
{
    /**
     * Tell ruleset generator (indirectly) that this validator only accepts
     * bool|int|string|null as allowed values.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator::enum()
     *
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const PATTERN_RULES = [
        'enum' => Type::EQUATABLE_NULL,
    ]
    + UncheckedValidator::PATTERN_RULES;
}
