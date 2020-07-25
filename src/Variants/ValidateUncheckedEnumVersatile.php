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
use SimpleComplex\Validate\ValidateUnchecked;
use SimpleComplex\Validate\RuleTraits\EnumVersatileTrait;

/**
 * Unchecked validator with enum() supporting float and null.
 *
 * @see ValidateUnchecked
 *
 *
 * @package SimpleComplex\Validate
 */
class ValidateUncheckedEnumVersatile extends ValidateUnchecked
{
    use EnumVersatileTrait;

    /**
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const PATTERN_RULES = [
        /**
         * enum() supports bool|int|float|string|null,
         * but accommodates to this type setting.
         * @see EnumVersatileTrait::enum()
         */
        'enum' => Type::SCALAR_NULLABLE,
    ]
    + ValidateUnchecked::PATTERN_RULES;
}
