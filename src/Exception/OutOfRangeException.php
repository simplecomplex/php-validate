<?php

namespace SimpleComplex\Validate\Exception;

/**
 * To differentiate exceptions thrown in-package from exceptions thrown
 * out-package.
 *
 * Please do not use - throw - in code of another package/library.
 *
 * @package SimpleComplex\Validate
 */
class OutOfRangeException extends \OutOfRangeException
{
}
