<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSetFactory;

use SimpleComplex\Validate\Helper\AbstractRule;
use SimpleComplex\Validate\Rule;

/**
 * Helper object used when creating ruleset.
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
class RuleSetRule extends AbstractRule
{
    /**
     * Eventually true|array.
     * @see RuleSetGenerator::resolveCandidates()
     *
     * @var mixed
     */
    public $argument;

    /**
     * @see RuleSetGenerator::ruleByValue()
     *
     * @var int|null
     */
    public $passedByValueAtIndex;

    /**
     * @param Rule $rule
     * @param mixed $argument
     * @param int $passedByValueAtIndex
     */
    public function __construct(Rule $rule, $argument, int $passedByValueAtIndex = null)
    {
        foreach ($rule as $key => $value) {
            $this->{$key} = $value;
        }
        $this->argument = $argument;
        $this->passedByValueAtIndex = $passedByValueAtIndex;
    }
}
