<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Helper\AbstractRule;

/**
 * Helper object used when creating ruleset.
 *
 * @package SimpleComplex\Validate
 */
class Rule extends AbstractRule
{
    /**
     * @param string $name
     * @param bool $isTypeChecking
     * @param int $type
     */
    public function __construct(string $name, bool $isTypeChecking, int $type)
    {
        $this->name = $name;
        $this->isTypeChecking = $isTypeChecking;
        $this->type = $type;
    }
}
