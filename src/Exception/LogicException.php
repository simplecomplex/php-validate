<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */

namespace SimpleComplex\Validate\Exception;

/**
 * To differentiate exceptions thrown in-package from exceptions thrown
 * out-package.
 *
 * Please do not throw this in code of library that doesn't extend this library.
 *
 * @package SimpleComplex\Validate
 */
class LogicException extends \LogicException implements ValidationException
{
}