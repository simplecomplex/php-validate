<?php /** @noinspection PhpUnused */
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;

use SimpleComplex\Validate\Helper\Helper;

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
     * Public non-rule instance methods.
     *
     * @var mixed[]
     */
    protected const NON_RULE_METHODS =
        RuleProviderInterface::PROVIDER_NON_RULE_METHODS
        + [
            '__call' => null,
        ];

    /**
     * Types of rules that explicitly promise to check the subject's type.
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then RuleSetGenerator makes a guess.
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @see TypeRulesInterface::MINIMAL_TYPE_RULES
     * @see getRuleNames()
     * @see getRule()
     * @see getTypeRuleType()
     * @see patternRuleToTypeRule()
     *
     * @var mixed[]
     */
    protected const TYPE_RULES = [];

    /**
     * Types of rules that don't promise to check the subject's type.
     *
     * @see PatternRulesInterface::MINIMAL_PATTERN_RULES
     * @see getPatternRuleType()
     *
     * Used by RuleSetGenerator to secure a type checking rule when none such
     * mentioned in the source of a validation rule set (e.g. JSON).
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @var int[]
     */
    protected const PATTERN_RULES = [];

    /**
     * Number of required parameters, by rule name.
     *
     * @var int[]
     */
    protected const PARAMS_REQUIRED = [];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule method name.
     *
     * @var int[]
     */
    protected const PARAMS_ALLOWED = [];

    /**
     * New rule name by old rule name.
     *
     * @see getRule()
     *
     * @var string[]
     */
    protected const RULES_RENAMED = [];

    /**
     * Flags controlling behaviours of rules.
     *
     * @var mixed[]
     */
    protected const RULE_FLAGS = [];

    /**
     * Cache of Rule objects.
     *
     * @see getRule()
     *
     * @var Rule[]
     */
    protected $rules = [];

    /**
     * Cache list of type rule names by type.
     *
     * @see patternRuleToTypeRule()
     *
     * @var string[][]
     */
    protected $typeRulesByType;

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
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        return static::$instanceByClass[$class] ??
            // Child class constructor may have parameters.
            (static::$instanceByClass[$class] = new static(...$constructorParams));
    }

    /**
     * List of public instance methods that are't rules.
     *
     * @see RuleProviderIntegrity
     * @see NON_RULE_METHODS
     *
     * @return string[]
     *
     * @see AbstractRuleProvider::getNonRuleNames()
     */
    public function getNonRuleNames() : array
    {
        return array_keys(static::NON_RULE_METHODS);
    }

    /**
     * Lists names of validation rule methods.
     *
     * @param bool $typeRulesOnly
     * @param bool $patternRulesOnly
     *
     * @return string[]
     *
     * @throws \InvalidArgumentException
     *      Both args falsy.
     */
    public function getRuleNames(bool $typeRulesOnly = false, bool $patternRulesOnly = false) : array
    {
        if ($typeRulesOnly && $patternRulesOnly) {
            throw new \InvalidArgumentException('Args $typeRulesOnly and $patternRulesOnly cannot both be true.');
        }
        if ($typeRulesOnly) {
            return array_keys(static::TYPE_RULES);
        }
        if ($patternRulesOnly) {
            return array_keys(static::PATTERN_RULES);
        }
        return array_merge(
            array_keys(static::TYPE_RULES),
            array_keys(static::PATTERN_RULES)
        );
    }

    /**
     * Get object describing the rule.
     *
     * Handles that the rule may be renamed.
     * Thus caller better from now on use Rule::$name instead the possibly
     * old initial name.
     *
     * @see RuleSetGenerator
     *
     * @param string $name
     *
     * @return Rule|null
     *      Null: nonexisting rule.
     */
    public function getRule(string $name) : ?Rule
    {
        $rule = $this->rules[$name] ?? null;
        if ($rule) {
            return $rule;
        }

        $name_final = static::RULES_RENAMED[$name] ?? null;
        if ($name_final) {
            $renamed = true;
        }
        else {
            $renamed = false;
            $name_final = $name;
        }

        $type = static::TYPE_RULES[$name_final] ?? null;
        if ($type) {
            $isTypeChecking = true;
        }
        else {
            $type = static::PATTERN_RULES[$name_final] ?? null;
            if (!$type) {
                return null;
            }
            $isTypeChecking = false;
        }

        $rule = new Rule($name_final, $isTypeChecking, $type);
        if ($renamed) {
            $rule->renamedFrom = $name;
        }

        $params = static::PARAMS_REQUIRED[$name_final] ?? 0;
        if ($params) {
            $rule->paramsRequired = $params;
        }
        $params = static::PARAMS_ALLOWED[$name_final] ?? $params;
        if ($params) {
            $rule->paramsAllowed = $params;
        }

        $this->rules[$name_final] = $rule;
        return $rule;
    }

    /**
     * Get type affiliation of a type-checking rule.
     *
     * @see ValidateAgainstRuleSet::internalChallenge()
     *
     * @see Type
     * @uses TYPE_RULES
     * @uses AbstractValidate::TYPE_RULES
     * @uses Validate::TYPE_RULES
     *
     * @param string $name
     *
     * @return int|null
     */
    public function getTypeRuleType(string $name) : ?int
    {
        return static::TYPE_RULES[$name] ?? null;
    }

    /**
     * Get type affiliation of a pattern rule.
     *
     * @see ValidateAgainstRuleSet::internalChallenge()
     *
     * @see Type
     * @uses PATTERN_RULES
     * @uses AbstractValidate::PATTERN_RULES
     * @uses Validate::PATTERN_RULES
     *
     * @param string $name
     *
     * @return int|null
     *
     * @see AbstractRuleProvider::PATTERN_RULES
     * @see Type
     *
     * For ValidateAgainstRuleSet.
     */
    public function getPatternRuleType(string $name) : ?int
    {
        return static::PATTERN_RULES[$name] ?? null;
    }

    /**
     * Get type rule fitting as type-checker before a pattern rule.
     *
     * For ruleset generator.
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * @param int|null $patternType
     *      Required if no $patternRuleName,
     *      ignored if $patternRuleName.
     * @param string|null $patternRuleName
     *
     * @return string|null
     *      Null: no such pattern rule, or type rule type, found.
     *
     * @throws \InvalidArgumentException
     *      Both arguments falsy.
     */
    public function patternRuleToTypeRule(int $patternType = null, string $patternRuleName = null) : ?string
    {
        if ($patternRuleName) {
            $type = static::PATTERN_RULES[$patternRuleName] ?? null;
        }
        elseif (!$patternType) {
            throw new \InvalidArgumentException(
                'Args $patternType type[' . Helper::getType($patternType) . '] and $patternRuleName['
                . Helper::getType($patternRuleName) . '] cannot both be falsy.'
            );
        }
        else {
            $type = $patternType;
        }
        if ($type) {
            // Create lists of type rule names by type.
            if (!$this->typeRulesByType) {
                $typeRulesByType = [];
                foreach (static::TYPE_RULES as $typeRuleName => $typeRuleType) {
                    if (!isset($typeRulesByType[$typeRuleType])) {
                        $typeRulesByType[$typeRuleType] = [$typeRuleName];
                    }
                    else {
                        $typeRulesByType[$typeRuleType][] = $typeRuleName;
                    }
                }
                $this->typeRulesByType =& $typeRulesByType;
            }

            $typeRules = $this->typeRulesByType[$type] ?? null;
            if ($typeRules) {
                return reset($typeRules);
            }
        }
        return null;
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


//    // Validate by list of rules.---------------------------------------------------------------------------------------
//
//    /**
//     * Validate by a list of rules.
//     *
//     * Stops on first failure.
//     *
//     * Reuses the same ValidateAgainstRuleSet across Validate instances
//     * and calls to this method.
//     *
//     * @param mixed $subject
//     * @param RuleSet\ValidationRuleSet|array|object $ruleSet
//     *
//     * @return bool
//     *
//     * @throws \Throwable
//     *      Propagated.
//     */
//    public function challenge($subject, $ruleSet) : bool
//    {
//        // Re-uses instance on ValidateAgainstRuleSet rules.
//        // Since we pass this object to the ValidateAgainstRuleSet instance,
//        // we shan't refer the ValidateAgainstRuleSet instance directly.
//        return ValidateAgainstRuleSet::getInstance(
//            $this
//        )->challenge($subject, $ruleSet);
//    }
//
//    /**
//     * Validate by a list of rules, recording validation failures.
//     *
//     * Doesn't stop on failure, continues until the end of the ruleset.
//     *
//     * Creates a new ValidateAgainstRuleSet instance on every call.
//     *
//     * @code
//     * $good_bike = Validate::make()->challengeRecording($bike, $rules);
//     * if (empty($good_bike['passed'])) {
//     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
//     * }
//     * @endcode
//     *
//     * @param mixed $subject
//     * @param RuleSet\ValidationRuleSet|array|object $ruleSet
//     * @param string $keyPath
//     *      Name of element to validate, or key path to it.
//     *
//     * @return array {
//     *      @var bool passed
//     *      @var array record
//     * }
//     *
//     * @throws \Throwable
//     *      Propagated.
//     */
//    public function challengeRecording($subject, $ruleSet, string $keyPath = 'root') : array
//    {
//        $validate_by_rules = new ValidateAgainstRuleSet($this, [
//            'recordFailure' => true,
//        ]);
//
//        $passed = $validate_by_rules->challenge($subject, $ruleSet, $keyPath);
//        return [
//            'passed' => $passed,
//            'record' => $passed ? [] : $validate_by_rules->getRecord(),
//        ];
//    }


    // Rule methods speficified by RuleProviderInterface
    // must be implemented by extending class.
}
