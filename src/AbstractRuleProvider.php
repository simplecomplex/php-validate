<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;

use SimpleComplex\Validate\Exception\BadMethodCallException;

/**
 * Scaffold of a validator usable for ruleset validation.
 *
 *
 * @package SimpleComplex\Validate
 */
abstract class AbstractRuleProvider implements RuleProviderInterface
{
    /**
     * Extending class must not override this variable.
     *
     * @var Validate[]
     * @final
     */
    protected static $instanceByClass;

    /**
     * Class-aware factory method.
     *
     * First object instantiated via this method, being class of class called on.
     *
     * @see challenge()
     *
     * @param mixed ...$constructorParams
     *      Validate child class constructor may have parameters.
     *
     * @return AbstractRuleProvider|static
     */
    public static function getInstance(...$constructorParams)
    {
        $class = get_called_class();
        return static::$instanceByClass[$class] ??
            // IDE: child class constructor may have parameters.
            (static::$instanceByClass[$class] = new static(...$constructorParams));
    }

    /**
     * Non-rule methods.
     *
     * @see getRuleMethods()
     *
     * Keys are property names, values may be anything.
     * Allows a child class to extend parent's list by doing
     * const NON_RULE_METHODS = [
     *   'someMethod' => true,
     * ] + ParentClass::NON_RULE_METHODS;
     *
     * @var mixed[]
     */
    const NON_RULE_METHODS = [
        'getInstance' => null,
        '__construct' => null,
        'getRuleMethods' => null,
        'getRulesRenamed' => null,
        'getTypeRules' => null,
        'getTypeInference' => null,
        'getParameterSpecs' => null,
        '__call' => null,
        'challenge' => null,
        'challengeRecording' => null,
    ];

    /**
     * Rules that explicitly promise to check the subject's type.
     *
     * @see getTypeRules()
     * @see TypeRulesInterface::MINIMAL_TYPE_RULES
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then RuleSetGenerator makes a guess.
     * @see TYPE_INFERENCE
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * Keys are methods names, values may be anything.
     * Allows a child class to extend parent's list by doing
     * const SOME_CONSTANT = [
     *   'someMethod' => null,
     * ] + ParentClass::SOME_CONSTANT;
     *
     * @var mixed[]
     */
    const TYPE_RULES = [];

    /**
     * Methods that don't do type-checking, and what type they implicitly
     * expects.
     *
     * @see getTypeInference()
     *
     * Used by RuleSetGenerator to secure a type checking rule when none such
     * mentioned in the source of a validation rule set (e.g. JSON).
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @var int[]
     */
    const TYPE_INFERENCE = [];

    /**
     * Number of required parameters, by rule name.
     *
     * @var int[]
     */
    const PARAMS_REQUIRED = [];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule method name.
     *
     * @var int[]
     */
    const PARAMS_ALLOWED = [];

    /**
     * New rule name by old rule name.
     *
     * @see getRulesRenamed()
     *
     * @var string[]
     */
    const RULES_RENAMED = [];


    /**
     * Instance vars are not allowed to have state
     * -------------------------------------------
     * except general instance info.
     * Because that could affect the challenge() method, making calls leak state
     * to eachother.
     * Would void ValidateAgainstRuleSet::getInstance()'s warranty that
     * requested and returned instance are effectively identical.
     *
     * @see challenge()
     * @see ValidateAgainstRuleSet::getInstance()
     */

    /**
     * @see getRuleMethods()
     *
     * @var string[]
     */
    protected $ruleMethods = [];

    /**
     * @see getTypeRules()
     *
     * @var string[]
     */
    protected $typeMethods = [];


    /**
     * Lists names of validation rule methods.
     *
     * @return string[]
     *
     * @throws \TypeError  Propagated.
     * @throws \InvalidArgumentException  Propagated.
     */
    public function getRuleMethods() : array
    {
        if (!$this->ruleMethods) {
            $this->ruleMethods = array_diff(
                Helper::getPublicMethods($this),
                array_keys(static::NON_RULE_METHODS)
            );
        }
        return $this->ruleMethods;
    }

    /**
     * Lists rule methods renamed.
     *
     * Keys is old name, value new name.
     *
     * @return string[]
     */
    public function getRulesRenamed() : array
    {
        return static::RULES_RENAMED;
    }

    /**
     * Type checking rules, and their type family.
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then ValidationRuleSet makes a guess; ultimately string.
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @return string[]
     */
    public function getTypeRules() : array
    {
        return static::TYPE_RULES;
    }

    /**
     * Rules that don't promise to be type-checking, and what type they
     * implicitly expect.
     *
     * Used by RuleSetGenerator to secure a type checking rule when none such
     * mentioned in the source of a validation rule set (e.g. JSON).
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @return int[]
     */
    public function getTypeInference() : array
    {
        return static::TYPE_INFERENCE;
    }

    /**
     * Two lists of number of required/allowed arguments.
     *
     * Number of required parameters, by rule method name.
     * @see AbstractValidate::PARAMS_REQUIRED
     *
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule method name.
     * @see AbstractValidate::PARAMS_ALLOWED
     *
     * @return int[][] {
     *      @var int[] $required
     *      @var int[] $allowed
     * }
     */
    public function getParameterSpecs() : array
    {
        return [
            'required' => static::PARAMS_REQUIRED,
            'allowed' => static::PARAMS_ALLOWED,
        ];
    }

    /**
     * By design, ValidateAgainstRuleSet::challenge() should not be able to call
     * a non-existent method of this class.
     * But external call to Validate::noSuchRule() is somewhat expectable.
     *
     * @see ValidateAgainstRuleSet::challenge()
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws BadMethodCallException
     *      Undefined rule method by arg name.
     */
    public function __call($name, $arguments)
    {
        throw new BadMethodCallException('Undefined validation rule[' . $name . '].');
    }


    // Validate by list of rules.---------------------------------------------------------------------------------------

    /**
     * Validate by a list of rules.
     *
     * Stops on first failure.
     *
     * Reuses the same ValidateAgainstRuleSet across Validate instances
     * and calls to this method.
     *
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return bool
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($subject, $ruleSet) : bool
    {
        // Re-uses instance on ValidateAgainstRuleSet rules.
        // Since we pass this object to the ValidateAgainstRuleSet instance,
        // we shan't refer the ValidateAgainstRuleSet instance directly.
        return ValidateAgainstRuleSet::getInstance(
            $this
        )->challenge($subject, $ruleSet);
    }

    /**
     * Validate by a list of rules, recording validation failures.
     *
     * Doesn't stop on failure, continues until the end of the ruleset.
     *
     * Creates a new ValidateAgainstRuleSet instance on every call.
     *
     * @code
     * $good_bike = Validate::make()->challengeRecording($bike, $rules);
     * if (empty($good_bike['passed'])) {
     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
     * }
     * @endcode
     *
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     * @param string $keyPath
     *      Name of element to validate, or key path to it.
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challengeRecording($subject, $ruleSet, string $keyPath = 'root') : array
    {
        $validate_by_rules = new ValidateAgainstRuleSet($this, [
            'recordFailure' => true,
        ]);

        $passed = $validate_by_rules->challenge($subject, $ruleSet, $keyPath);
        return [
            'passed' => $passed,
            'record' => $passed ? [] : $validate_by_rules->getRecord(),
        ];
    }


    // Rule methods speficified by RuleProviderInterface
    // must be implemented by extending class.
}
