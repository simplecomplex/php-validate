<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSetFactory;

/**
 * Helper object used when creating ruleset.
 *
 * @package SimpleComplex\Validate
 */
class RuleSetRule
{
    /**
     * @var string
     */
    public $name;

    /**
     * Eventually true|array.
     * @see RuleSetGenerator::resolveCandidates()
     *
     * @var mixed
     */
    public $argument;

    /**
     * @var int|null
     */
    public $passedByValueAtIndex;

    /**
     * @var int
     */
    public $paramsAllowed = 0;

    /**
     * @var int
     */
    public $paramsRequired = 0;

    /**
     * @var string|null
     */
    public $renamedFrom;

    /**
     * @param string $name
     * @param mixed $argument
     * @param int $passedByValueAtIndex
     */
    public function __construct(string $name, $argument, int $passedByValueAtIndex = null)
    {
        $this->name = $name;
        $this->argument = $argument;
        $this->passedByValueAtIndex = $passedByValueAtIndex;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function rename(string $name) : self
    {
        $this->renamedFrom = $this->name;
        $this->name = $name;
        return $this;
    }
}
