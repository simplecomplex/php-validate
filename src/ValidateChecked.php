<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Traits\ValidateCheckedTrait;

/**
 *
 * @package SimpleComplex\Validate
 */
class ValidateChecked extends ValidateUnchecked
{
    use ValidateCheckedTrait;

    /**
     * All methods are type-checking.
     *
     * @see ValidateUnchecked::TYPE_METHODS
     */
    const TYPE_METHODS = [
        'bit32' => null,
        'bit64' => null,
        'positive' => null,
        'nonNegative' => null,
        'negative' => null,
        'min' => null,
        'max' => null,
        'range' => null,
        'regex' => null,
        'unicode' => null,
        'unicodePrintable' => null,
        'unicodeMultiLine' => null,
        'unicodeMinLength' => null,
        'unicodeMaxLength' => null,
        'unicodeExactLength' => null,
        'hex' => null,
        'ascii' => null,
        'asciiPrintable' => null,
        'asciiMultiLine' => null,
        'minLength' => null,
        'maxLength' => null,
        'exactLength' => null,
        'alphaNum' => null,
        'name' => null,
        'camelName' => null,
        'snakeName' => null,
        'lispName' => null,
        'uuid' => null,
        'base64' => null,
        'dateISO8601' => null,
        'dateISO8601Local' => null,
        'timeISO8601' => null,
        'dateTimeISO8601' => null,
        'dateTimeISO8601Local' => null,
        'dateTimeISO8601Zonal' => null,
        'dateTimeISOUTC' => null,
        'plainText' => null,
        'ipAddress' => null,
        'url' => null,
        'httpUrl' => null,
        'email' => null,
    ]
    + ValidateUnchecked::TYPE_METHODS;

    /**
     * No need for type inference at all.
     *
     * @see ValidateUnchecked::TYPE_INFERENCE
     */
    const TYPE_INFERENCE = [];
}
