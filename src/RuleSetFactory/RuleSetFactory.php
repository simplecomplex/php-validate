<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSetFactory;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\RuleSet\ValidationRuleSet;

/**
 * Creates validation rulesets recursively.
 *
 * See the generator and ruleset classes for details.
 * @see RuleSetGenerator
 * @see ValidationRuleSet
 *
 * Design technicalities
 * ---------------------
 * Holds the rule provider for ruleset generator(s).
 * Spawns generators for ruleset TableElements|ListItems.
 * @see \SimpleComplex\Validate\RuleSet\TableElements::defineRulesByElements()
 * @see \SimpleComplex\Validate\RuleSet\ListItems::defineItemRules()
 *
 * @package SimpleComplex\Validate
 */
class RuleSetFactory
{
    /**
     * @var string
     */
    protected const CLASS_GENERATOR = RuleSetGenerator::class;

    /**
     * @var RuleProviderInterface
     */
    public $ruleProvider;

    /**
     * @param RuleProviderInterface $ruleProvider
     */
    public function __construct(RuleProviderInterface $ruleProvider)
    {
        $this->ruleProvider = $ruleProvider;
    }

    /**
     * @param object|array $rules
     *      ArrayAccess is not supported.
     * @param int $depth
     * @param string $keyPath
     *
     * @return ValidationRuleSet
     */
    public function make($rules, int $depth = 0, string $keyPath = 'root') : ValidationRuleSet
    {
        $class = static::CLASS_GENERATOR;
        /** @var RuleSetGenerator $generator */
        $generator = new $class($this, $rules, $depth, $keyPath);

        return $generator->generate();
    }
}
