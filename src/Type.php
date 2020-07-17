<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;


class Type
{
    const NUMERIC = 4;

    const STRINGABLE = 64;

    const LOOPABLE = 1024;


    const RULES_NUMERIC = [
        'integer', 'float', 'number', 'digital', 'numeric',
    ];

    const RULES_STRINGABLE = [
        'string', 'stringableScalar', 'stringableObject', 'stringable',
    ];

    const RULES_LOOPABLE = [
        'indexedArray', 'keyedArray', 'array', 'indexedLoopable', 'keyedLoopable', 'loopable',
    ];
}
