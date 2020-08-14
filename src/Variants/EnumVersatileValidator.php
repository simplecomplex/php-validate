<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Variants;

use SimpleComplex\Validate\Interfaces\CheckedRuleProviderInterface;

use SimpleComplex\Validate\Type;
use SimpleComplex\Validate\Validator;
use SimpleComplex\Validate\RuleTraits\EnumVersatileTrait;

/**
 * Checked validator with enum() supporting float and null.
 *
 * @see Validator
 *
 * Unchecked counterpart:
 * @see EnumVersatileUncheckedValidator
 *
 * @package SimpleComplex\Validate
 */
class EnumVersatileValidator extends Validator implements CheckedRuleProviderInterface
{
    use EnumVersatileTrait;

    /**
     * In an all type-checking validator all rules are type-rules.
     * @see Validator::TYPE_RULES
     * @see Validator::PATTERN_RULES
     *
     * Overrides by prepending overriding bucket(s);
     * PHP array union(+) ignores duplicate in righthand array.
     */
    protected const TYPE_RULES = [
        /**
         * enum() supports bool|int|float|string|null,
         * but accommodates to this type setting.
         * @see EnumVersatileTrait::enum()
         */
        'enum' => Type::SCALAR_NULL,
    ]
    + Validator::TYPE_RULES;
}
