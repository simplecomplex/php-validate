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
 * Object describing a rule.
 *
 * @see AbstractRuleProvider::getRule()
 *
 * Inherited:
 * @property string $name
 * @property bool $isTypeChecking
 * @property int $type
 * @property int $paramsRequired Default: 0.
 * @property int $paramsAllowed Default: 0.
 * @property string|null $renamedFrom Default: null.
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
