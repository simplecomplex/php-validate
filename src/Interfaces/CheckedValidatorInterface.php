<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

/**
 * Rule provider which promises that all it's rule methods
 * are type-checking in themselves.
 *
 * @package SimpleComplex\Validate
 */
interface CheckedValidatorInterface extends RuleProviderInterface
{
}
