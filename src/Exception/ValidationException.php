<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */

namespace SimpleComplex\Validate\Exception;

/**
 * Error detected during a validation process.
 *
 * All in-package exceptions of this library implements this interface.
 * Please do not use this in code of library that doesn't extend this library.
 *
 * Extends \Throwable to be catchable.
 *
 * @package SimpleComplex\Validate\Exception
 */
interface ValidationException extends \Throwable
{
}
