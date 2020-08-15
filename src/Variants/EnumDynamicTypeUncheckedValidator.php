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
use SimpleComplex\Validate\RuleTraits\EnumDynamicTypeTrait;

/**
 * Unchecked validator with enum() supporting float and null.
 *
 * @see UncheckedValidator
 *
 * Checked counterpart:
 * @see EnumDynamicTypeValidator
 *
 * @package SimpleComplex\Validate
 */
class EnumDynamicTypeUncheckedValidator extends UncheckedValidator
{
    use EnumDynamicTypeTrait;

    /**
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const PATTERN_RULES = [
        /**
         * enum() supports bool|int|float|string|null,
         * but accommodates to this type setting.
         * @see EnumDynamicTypeTrait::enum()
         */
        'enum' => Type::SCALAR_NULL,
    ]
    + UncheckedValidator::PATTERN_RULES;
}
