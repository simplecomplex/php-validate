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
use SimpleComplex\Validate\Interfaces\CheckedValidatorInterface;

use SimpleComplex\Validate\Helper\AbstractRule;
use SimpleComplex\Validate\Helper\Rule;
use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\BadMethodCallException;

/**
 * Scaffold of a validator usable for shallow non-ruleset validation
 * and/or recursive validation by ruleset.
 *
 * @package SimpleComplex\Validate
 */
abstract class AbstractRuleProvider implements RuleProviderInterface
{
    /**
     * The Type class use for registering rules.
     *
     * @see \SimpleComplex\Validate\Interfaces\TypeRulesInterface
     * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface
     *
     * @var string
     */
    public const TYPE_CLASS = Type::class;

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
     * The number excludes the (first) subject parameter.
     *
     * @var int[]
     */
    protected const PARAMS_REQUIRED = [];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule method name.
     *
     * The number excludes the (first) subject parameter.
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
     * @var AbstractRule[]
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
     * @var AbstractRuleProvider[]
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
     *      Child class constructor may have parameters.
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
     * Lists names of validation rule methods.
     *
     * @param bool $typeRulesOnly
     * @param bool $patternRulesOnly
     *
     * @return string[]
     *
     * @throws InvalidArgumentException
     *      Both args falsy.
     */
    public function getRuleNames(bool $typeRulesOnly = false, bool $patternRulesOnly = false) : array
    {
        if ($typeRulesOnly && $patternRulesOnly) {
            throw new InvalidArgumentException('Args $typeRulesOnly and $patternRulesOnly cannot both be true.');
        }
        if ($typeRulesOnly) {
            return array_keys(static::TYPE_RULES);
        }
        if ($patternRulesOnly) {
            return array_keys(static::PATTERN_RULES);
        }
        return array_keys(static::TYPE_RULES + static::PATTERN_RULES);
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
     * @return AbstractRule|null
     *      Null: nonexisting rule.
     */
    public function getRule(string $name) : ?AbstractRule
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
     * @param string $name
     *
     * @return int|null
     *
     * @see AbstractRuleProvider::TYPE_RULES
     * @see ValidateAgainstRuleSet::internalChallenge()
     * @see Type
     */
    public function getTypeRuleType(string $name) : ?int
    {
        return static::TYPE_RULES[$name] ?? null;
    }

    /**
     * Get type affiliation of a pattern rule.
     *
     * @param string $name
     *
     * @return int|null
     *
     * @see AbstractRuleProvider::PATTERN_RULES
     * @see ValidateAgainstRuleSet::internalChallenge()
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
     * Ensures that the rule method doesn't require parameters.
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
     * @throws InvalidArgumentException
     *      Both arguments falsy.
     */
    public function patternRuleToTypeRule(int $patternType = null, string $patternRuleName = null) : ?string
    {
        if ($patternRuleName) {
            $type = static::PATTERN_RULES[$patternRuleName] ?? null;
        }
        elseif (!$patternType) {
            throw new InvalidArgumentException(
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
                        // Check that the rule doesn't require parameters.
                        if (!$this->getRule($typeRuleName)->paramsRequired) {
                            $typeRulesByType[$typeRuleType] = [$typeRuleName];
                        }
                        // Otherwise wait until non-parameter rule turns up.
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
     * Whereas external call to CheckedValidator::noSuchRule() is expectable.
     *
     * @see ValidateAgainstRuleSet::challenge()
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws BadMethodCallException
     *      Undefined rule method by arg name.
     */
    public function __call(string $name, array $arguments)
    {
        throw new BadMethodCallException('Undefined validation rule[' . $name . '].');
    }

    /**
     * Checks that all information about the rule provider's rule methods
     * is correct; type, number of parameters etc.
     *
     * First checks that all integer class constants of the Type class used
     * are unique.
     * @see TYPE_CLASS
     *
     * Method used by rule-provider integrity test.
     * @see \SimpleComplex\Tests\Validate\RuleProviderIntegrityTest
     *
     * @return string[]
     *
     * @throws \ReflectionException
     */
    public function getIntegrity() : array
    {
        $msgs = [];

        // Do all integer constants of the Type class have unique values?
        $types = (new \ReflectionClass(static::TYPE_CLASS))->getConstants();
        $type_by_value = [];
        $passed = true;
        foreach ($types as $typeName => $typeValue) {
            if (is_int($typeValue)) {
                if (array_key_exists($typeValue, $type_by_value)) {
                    $msgs[] = 'value[' . $typeValue . '] constants['
                        . $type_by_value[$typeValue] . ', ' . $typeName . ']';
                    $passed = false;
                }
                else {
                    $type_by_value[$typeValue] = $typeName;
                }
            }
        }
        if (!$passed) {
            // Get out, can't check type of rules when dupes.
            return [
                'The Type class[' . static::TYPE_CLASS
                . '] applied have non-unique integer constants: ' . join(', ', $msgs) . '.'
            ];
        }

        $provider_class = get_class($this);
        $registered_rule_names = $this->getRuleNames();
        $non_rule_methods = array_keys(static::NON_RULE_METHODS);
        $public_methods = Helper::getPublicMethods($provider_class, true);
        $actual_rule_names = array_values(array_diff($public_methods, $non_rule_methods));
        $pattern_rules = [];

        $c_rflctn = (new \ReflectionClass($this));
        $w = count($actual_rule_names);
        for ($i = 0; $i < $w; ++$i) {
            $name = $actual_rule_names[$i];

            // In getRuleNames()?
            $i_registered = array_search($name, $registered_rule_names, true);
            if ($i_registered === false) {
                $msgs[] = 'Public instance method[' . $name . '] is not registered as a rule'
                    . ', not in list from ::getRuleNames().';
            }
            else {
                array_splice($registered_rule_names, $i_registered, 1);
            }

            // In getRule()?
            $o_rule = $this->getRule($name);
            if (!$o_rule) {
                $msgs[] = 'Public instance method[' . $name . '] is not registered as a rule'
                    . ', not retrievable from ::getRule().';
                continue;
            }

            // Type in Type class constants?
            if (!array_key_exists($o_rule->type, $type_by_value)) {
                $msgs[] = 'Rule[' . $name . '] type[' . $o_rule->type . ']'
                    . ' is not a class constant type of Type class[' . static::TYPE_CLASS . '].';
            }
            if (!$o_rule->isTypeChecking) {
                $pattern_rules[] = $name;
            }

            // Parameters.
            $m_rflctn = $c_rflctn->getMethod($name);
            // -1 because registered number of parameters excludes
            // the (first) subject parameter.
            if ($o_rule->paramsRequired != $m_rflctn->getNumberOfRequiredParameters() - 1) {
                $msgs[] = 'Rule[' . $name . '] registered required parameters[' . $o_rule->paramsRequired . ']'
                    . ' doesn\'t match (subject param excluded) actual['
                    . ($m_rflctn->getNumberOfRequiredParameters() - 1) . '].';
            }
            if ($o_rule->paramsAllowed != $m_rflctn->getNumberOfParameters() - 1) {
                $msgs[] = 'Rule[' . $name . '] registered allowed parameters[' . $o_rule->paramsAllowed . ']'
                    . ' doesn\'t match (subject param excluded) actual['
                    . ($m_rflctn->getNumberOfParameters() - 1) . '].';
            }
        }

        // Non type-checking rules despite promising that all are type-checking?
        if ($pattern_rules && $provider_class instanceof CheckedValidatorInterface) {
            $msgs[] = 'Implements CheckedValidatorInterface but has some non type-checking (pattern) rules['
                . join(', ', $pattern_rules) . '].';
        }

        // Declares rules that aren't actual methods?
        if ($registered_rule_names) {
            $msgs[] = 'Some rules declared by ::getRuleNames() do not exist as public instance methods, rules['
                . join(', ', $registered_rule_names) . '].';
        }

        // Has declared non-rule methods that don't exist?
        foreach ($non_rule_methods as $name) {
            if (array_search($name, $public_methods, true) === false) {
                $msgs[] = 'Non-rule method[' . $name . '] doesn\'t exist.';
            }
        }

        return $msgs;
    }

    // Rule methods speficified by RuleProviderInterface
    // must be implemented by extending class.
}
