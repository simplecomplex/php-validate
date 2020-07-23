<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Helper;

/**
 * Helper object used when creating ruleset.
 *
 * @package SimpleComplex\Validate
 */
class AbstractRule
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var bool
     */
    public $isTypeChecking;

    /**
     * @see Type
     *
     * @var int
     */
    public $type;

    /**
     * Number of method parameters required.
     *
     * @var int
     */
    public $paramsRequired = 0;

    /**
     * Number of method parameters allowed.
     *
     * @var int
     */
    public $paramsAllowed = 0;

    /**
     * @var string|null
     */
    public $renamedFrom;
}
