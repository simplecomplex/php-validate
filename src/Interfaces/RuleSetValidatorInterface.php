<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

use SimpleComplex\Validate\RuleSet\ValidationRuleSet;

/**
 * Validator using rulesets instead of plain rule methods.
 *
 * Supports recursive validation of object|array containers.
 *
 * @package SimpleComplex\Validate
 */
interface RuleSetValidatorInterface extends RuleProviderInterface
{
    /**
     * Bitmask flag: produce and record failure message(s).
     *
     * @var int
     */
    public const RECORD = 1;

    /**
     * Bitmask flag: continue on failure.
     *
     * Ignored onless recording.
     * @see RECORD
     *
     * @var int
     */
    public const CONTINUE = 2;

    /**
     * Public non-rule instance methods.
     *
     * Implementing class may do:
     * const NON_RULE_METHODS = RuleSetValidatorInterface::CHALLENGER_NON_RULE_METHODS;
     * Or use use PHP array union(+), like:
     * const NON_RULE_METHODS = [
     *   'someRule' => null,
     * ] + RuleSetValidatorInterface::CHALLENGER_NON_RULE_METHODS;
     *
     * @var mixed[]
     */
    public const CHALLENGER_NON_RULE_METHODS = [
        'validate' => null,
        'getLastFailure' => null,
    ];

    /**
     * Validate against a ruleset.
     *
     * @param mixed $subject
     * @param ValidationRuleSet|object|array $ruleSet
     * @param int $options
     *      Bitmask, see RuleSetValidatorInterface bitmask flag constants.
     *
     * @return bool
     *
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *      Propagated.
     */
    public function validate($subject, $ruleSet, int $options = 0) : bool;

    /**
     * Get failure(s) recorded by last recording validate().
     *
     * @param string $delimiter
     *
     * @return string
     */
    public function getLastFailure(string $delimiter = PHP_EOL) : string;
}
