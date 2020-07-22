<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Traits\PatternRulesCheckedTrait;

/**
 *
 * @package SimpleComplex\Validate
 */
class Validate extends AbstractValidate
{
    // Pattern rules that are type-checking.
    use PatternRulesCheckedTrait;

    /**
     * All methods are type-checking.
     *
     * @see ValidateUnchecked::TYPE_RULES
     */
    const TYPE_RULES =
        ValidateUnchecked::TYPE_RULES
        + ValidateUnchecked::TYPE_INFERENCE;

    /**
     * No need for type inference at all.
     *
     * @see ValidateUnchecked::TYPE_INFERENCE
     */
    const TYPE_INFERENCE = [];
}
