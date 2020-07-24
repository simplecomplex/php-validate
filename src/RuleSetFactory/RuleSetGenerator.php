<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSetFactory;

use SimpleComplex\Validate\Type;
use SimpleComplex\Validate\Rule;
use SimpleComplex\Validate\Helper\Helper;
use SimpleComplex\Validate\ValidationRuleSet;
use SimpleComplex\Validate\TableElements;
use SimpleComplex\Validate\ListItems;

use SimpleComplex\Validate\Exception\OutOfRangeException;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Creates a single validation ruleset,
 * possibly a child and/or parent of other rulesets.
 *
 * @see RuleSetFactory::make()
 *
 * @package SimpleComplex\Validate
 */
class RuleSetGenerator
{
    /**
     * Recursion emergency brake.
     *
     * @see ValidateAgainstRuleSet::RECURSION_LIMIT
     *
     * @var int
     */
    const RECURSION_LIMIT = 10;

    /**
     * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
     */

    /**
     * @var string
     */
    const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * @var string
     */
    const CLASS_TABLE_ELEMENTS = TableElements::class;

    /**
     * @var string
     */
    const CLASS_LIST_ITEMS = ListItems::class;

    /**
     * Pseudo rules that cannot be passed by value.
     *
     * @see ruleByValue()
     *
     * @var mixed[]
     */
    const ILLEGAL_BY_VALUE = [
        'alternativeEnum' => null,
        'alternativeRuleSet' => null,
        'tableElements' => null,
        'listItems' => null,
    ];

    /**
     * Child rules illegal for alternativeRuleSet.
     *
     * @var mixed[]
     */
    const ALTERNATIVE_RULESET_ILLEGALS = [
        'alternativeRuleSet' => null,
        'tableElements' => null,
        'listItems' => null,
    ];

    /**
     * @var RuleSetFactory
     */
    protected $factory;

    /**
     * @var object
     */
    protected $rulesRaw;

    /**
     * @var int
     */
    protected $depth;

    /**
     * @var string
     */
    protected $keyPath;

    /**
     * @var RuleSetRule[]
     */
    protected $ruleCandidates = [];

    /**
     * Goes at top of the ruleset, for clarity only.
     * Could go anywhere.
     *
     * @var bool|null
     */
    protected $optional;

    /**
     * Goes at top of the ruleset, for clarity only.
     *
     * @var bool|null
     */
    protected $nullable;

    /**
     * Type-checking rules must be the first real rules of the ruleset.
     *
     * @var RuleSetRule[]
     */
    protected $typeRules = [];

    /**
     * Goes after type-checking, but doesn't require type-checking.
     *
     * @var bool|null
     */
    protected $empty;

    /**
     * Goes after type-checking, but doesn't require type-checking.
     *
     * @var bool|null
     */
    protected $nonEmpty;

    /**
     * Non-type-checking rules must go after type-checking.
     *
     * @var RuleSetRule[]
     */
    protected $patternRules = [];

    /**
     * Goes after ordinary rules, for clarity only.
     *
     * @var mixed[]|null
     */
    protected $alternativeEnum;

    /**
     * Goes after ordinary rules, for clarity only.
     *
     * @var ValidationRuleSet|null
     */
    protected $alternativeRuleSet;

    /**
     * Goes at bottom, for clarity only.
     *
     * @var TableElements|null
     */
    protected $tableElements;

    /**
     * Goes at bottom, for clarity only.
     *
     * @var ListItems|null
     */
    protected $listItems;


    /**
     * @see RuleSetFactory::make()
     *
     * @param RuleSetFactory $factory
     * @param object|array $rules
     *      ArrayAccess is not supported.
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(RuleSetFactory $factory, $rules, int $depth = 0, string $keyPath = 'root')
    {
        // Constructor deliberately takes all the needed parameters,
        // to make it clear that a generator isn't reusable.
        // Dependency injection must take place on factory level.

        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation rule set definition at limit['
                . static::RECURSION_LIMIT . ']' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $this->factory = $factory;

        if (is_object($rules)) {
            if ($rules instanceof \ArrayAccess) {
                throw new \InvalidArgumentException(
                    'Arg rules type[' . Helper::getType($rules)
                    . '] \ArrayAccess is not supported' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $this->rulesRaw = $rules;
        }
        elseif (is_array($rules)) {
            $this->rulesRaw = (object) $rules;
        }
        else {
            throw new \TypeError(
                'Arg rules type[' . Helper::getType($rules)
                . '] is not object|array' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $this->depth = $depth;
        $this->keyPath = $keyPath;
    }

    /**
     * @return ValidationRuleSet
     */
    public function generate() : ValidationRuleSet
    {
        foreach ($this->rulesRaw as $ruleName => $argument) {
            if ($ruleName === '') {
                throw new InvalidRuleException(
                    'Validation ruleset key cannot be empty string \'\''
                    . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                );
            }
            // Simple non-parameter rule declared by value instead of key.
            elseif (ctype_digit('' . $ruleName)) {
                if (!$argument || !is_string($argument)) {
                    throw new InvalidRuleException(
                        'Validation rule-by-value type[' . Helper::getType($argument) . '] is not non-empty string'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
                // PHP numeric index is not consistently integer.
                $this->ruleByValue('' . $ruleName, $argument);
            }
            // Rule by key, value true|array.
            else {
                $this->ruleByKey($ruleName, $argument);
            }
        }

        $this->resolveCandidates();

        if (!$this->typeRules) {
            $this->ensureTypeChecking();
        }

        return $this->toRuleSet();
    }

    /**
     * @return ValidationRuleSet
     */
    protected function toRuleSet() : ValidationRuleSet
    {
        $class_ruleset = static::CLASS_RULE_SET;
        /** @var ValidationRuleSet $ruleset */
        $ruleset = new $class_ruleset();

        // At top for clarity.
        if ($this->optional) {
            $ruleset->optional = true;
        }
        if ($this->nullable) {
            $ruleset->nullable = true;
        }

        // Type-checking rules must be the first real rules of the ruleset.
        foreach ($this->typeRules as $rule) {
            $ruleset->{$rule->name} = $rule->argument;
        }

        // Goes after type-checking, but don't require type-checking.
        if ($this->empty) {
            $ruleset->empty = true;
        }
        elseif ($this->nonEmpty) {
            $ruleset->nonEmpty = true;
        }

        // Non-type-checking rules must go after type-checking.
        foreach ($this->patternRules as $rule) {
            $ruleset->{$rule->name} = $rule->argument;
        }

        // Pseudo rules go at bottom of the ruleset, for clarity only.
        foreach (['alternativeEnum', 'alternativeRuleSet', 'tableElements', 'listItems'] as $pseudoRule) {
            if ($this->{$pseudoRule}) {
                $ruleset->{$pseudoRule} = $this->{$pseudoRule};
            }
        }

        return $ruleset;
    }

    /**
     * Ensures that the ruleset will contain a type-checking method,
     * if necessary.
     *
     * Also checks that the resulting ruleset isn't effectively empty.
     *
     * @throws InvalidRuleException
     */
    protected function ensureTypeChecking() : void
    {
        // Sanity check.
        if ($this->typeRules) {
            return;
        }

        // Necessary?
        if (!$this->patternRules
            && !$this->tableElements
            && !$this->listItems
        ) {
            // Least possible conditions is empty|nonEmpty.
            if (!$this->empty && !$this->nonEmpty) {
                throw new InvalidRuleException(
                    'Validation ruleset '
                    . (!$this->alternativeEnum && !$this->alternativeRuleSet ? 'completely' : 'effectively') . ' empty'
                    . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                );
            }
            // Fine, simply checks empty|nonEmpty.
            return;
        }

        // tableElements|listItems require loopable container.
        if ($this->tableElements || $this->listItems) {
            $method = $this->factory->ruleProvider->patternRuleToTypeRule(Type::LOOPABLE);
            if (!$method) {
                throw new \LogicException(
                    'Rule provider ' . get_class($this->factory->ruleProvider)
                    . ' has no type rule matching type LOOPABLE.'
                );
            }
            // Safe to use $method as name, because cannot be renamed;
            // we got the name from ruleProvider right before.
            $this->typeRules[$method] = new RuleSetRule(
                $this->factory->ruleProvider->getRule($method),
                true
            );
            return;
        }

        // Find first pattern rule, and find type rule matching that.
        if (!$this->patternRules) {
            throw new \LogicException(__CLASS__ . '::$patternRules cannot be empty at this stage.');
        }
        $patternRule = reset($this->patternRules);
        $method = $this->factory->ruleProvider->patternRuleToTypeRule(null, $patternRule->name);
        if (!$method) {
            throw new \LogicException(
                'Rule provider ' . get_class($this->factory->ruleProvider)
                . ' has no type rule matching pattern rule[' . $patternRule->name . '].'
            );
        }
        // Safe to use $method as name, because cannot be renamed;
        // we got the name from ruleProvider right before.
        $this->typeRules[$method] = new RuleSetRule(
            $this->factory->ruleProvider->getRule($method),
            true
        );
    }

    /**
     * Checks arguments of rules, and passes them to lists
     * of either type-checking or pattern rules.
     */
    protected function resolveCandidates() : void
    {
        foreach ($this->ruleCandidates as $name => $rule) {
            // Check argument(s).
            if ($rule->argument === true) {
                if ($rule->paramsRequired) {
                    throw new InvalidRuleException(
                        $this->candidateErrorMsg(
                            $rule, ' requires array(' . $rule->paramsRequired . ') - saw type[true]'
                        )
                    );
                }
            }
            elseif (is_array($rule->argument)) {
                if (!$rule->argument && !$rule->paramsRequired && !$rule->paramsAllowed) {
                    // Allow empty array when no parameters supported.
                    $rule->argument = true;
                }
                else {
                    $n_args = count($rule->argument);
                    if ($n_args < $rule->paramsRequired) {
                        throw new InvalidRuleException(
                            $this->candidateErrorMsg(
                                $rule, ' requires array(' . $rule->paramsRequired . ') - saw array(' . $n_args . ')'
                            )
                        );
                    }
                    if ($n_args > $rule->paramsAllowed) {
                        if (!$rule->paramsAllowed) {
                            throw new InvalidRuleException(
                                $this->candidateErrorMsg(
                                    $rule, ' takes no arguments - saw array(' . count($rule->argument) . ')'
                                )
                            );
                        }
                        throw new InvalidRuleException(
                            $this->candidateErrorMsg(
                                $rule, ' supports array(' . $rule->paramsAllowed . ') - saw array(' . $n_args . ')'
                            )
                        );
                    }
                }
            }
            // Scalar but not true.
            elseif (is_scalar($rule->argument) /*&& $rule->argument !== true*/) {
                if ($rule->paramsAllowed && $rule->paramsRequired < 2) {
                    // Allow scalar (not true) if single parameter supported.
                    $rule->argument = [
                        $rule->argument
                    ];
                }
                else {
                    throw new InvalidRuleException(
                        $this->candidateErrorMsg(
                            $rule,
                            (
                                $rule->paramsRequired > 1 ? (' requires array(' . $rule->paramsRequired . ')') :
                                    (' takes no arguments')
                            )
                            . ' - saw type[' . Helper::getType($rule->argument) . ']'
                        )
                    );
                }
            }
            else {
                throw new InvalidRuleException(
                    $this->candidateErrorMsg(
                        $rule, ' invalid value type[' . Helper::getType($rule->argument) . ']'
                    )
                );
            }

            if ($rule->isTypeChecking) {
                $this->typeRules[$name] = $rule;
            }
            else {
                $this->patternRules[$name] = $rule;
            }
        }

        // All done, clear references.
        $this->ruleCandidates = [];
    }

    /**
     * Resolve rule defined as (key) rule-name -> (value) argument.
     *
     * @param string $ruleName
     * @param mixed $argument
     *
     * @throws InvalidRuleException
     */
    protected function ruleByKey(string $ruleName, $argument) : void
    {
        switch ($ruleName) {
            case 'optional':
                // Allow falsy, but then don't set.
                if ($argument) {
                    $this->optional = true;
                }
                break;
            case 'nullable':
            case 'allowNull':
                // Allow falsy, but then don't set.
                if ($argument) {
                    $this->nullable = true;
                }
                break;

            case 'empty':
            case 'nonEmpty':
                if (($this->empty && $ruleName == 'nonEmpty') || ($this->nonEmpty && $ruleName == 'empty')) {
                    throw new InvalidRuleException(
                        'Validation rules \'empty\' and \'nonEmpty\' cannot co-exist'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
                $this->{$ruleName} = true;
                break;

            case 'enum':
                // Safe to use enum as name; cannot be renamed.
                $rule = $this->factory->ruleProvider->getRule('enum');
                $enum = $this->enum($rule, $argument);
                if ($enum) {
                    $this->ruleCandidates['enum'] = new RuleSetRule($rule, [
                        $enum
                    ]);
                }
                // ...else: Ignore (don't set) if only contained null bucket.
                unset($rule, $enum);
                break;

            case 'alternativeEnum':
                $rule = $this->factory->ruleProvider->getRule('enum');
                $enum = $this->enum($rule, $argument, 'alternativeEnum');
                if ($enum) {
                    $this->alternativeEnum = $enum;
                }
                // ...else: Ignore (don't set) if only contained null bucket.
                unset($rule, $enum);
                break;

            case 'alternativeRuleSet':
                $class_ruleset = static::CLASS_RULE_SET;
                if ($argument instanceof $class_ruleset) {
                    $this->alternativeRuleSet = $argument;
                }
                else {
                    // new ValidationRuleSet(.
                    $this->alternativeRuleSet = $this->factory->make(
                        $argument, $this->depth + 1, $this->keyPath . '(alternativeRuleSet)'
                    );
                }
                // alternativeRuleSet illegal children.
                foreach (static::ALTERNATIVE_RULESET_ILLEGALS as $illegal_child) {
                    if (isset($this->alternativeRuleSet->{$illegal_child})) {
                        throw new InvalidRuleException(
                            'Validation \'alternativeRuleSet\' is not allowed to contain \'' . $illegal_child . '\''
                            . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                        );
                    }
                }
                break;

            case 'tableElements':
                // Declare dynamically.
                $this->tableElements = $this->tableElements($argument);
                break;

            case 'listItems':
                // Declare dynamically.
                $this->listItems = $this->listItems($argument);
                break;

            default:
                $rule = $this->factory->ruleProvider->getRule($ruleName);
                if (!$rule) {
                    throw new InvalidRuleException(
                        'Validation rule-by-key[' . $ruleName . '] is not supported'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
                $candidate = new RuleSetRule($rule, $argument);
                // Dupe?
                // Use Rule::name because may be renamed.
                if (isset($this->ruleCandidates[$rule->name])) {
                    throw new InvalidRuleException(
                        $this->candidateErrorMsg($candidate, ' conflicts with rule-by-value of same name')
                    );
                }
                // Use Rule::name because may be renamed.
                $this->ruleCandidates[$rule->name] = $candidate;
        }
    }

    /**
     * Resolve rule defined as (index) int -> (value) rule-name.
     *
     * @param string $index
     *      PHP numeric index is not consistently integer.
     * @param string $ruleName
     *
     * @throws InvalidRuleException
     */
    protected function ruleByValue(string $index, string $ruleName) : void
    {
        switch ($ruleName) {
            case 'optional':
                $this->optional = true;
                break;
            case 'nullable':
            case 'allowNull':
                $this->nullable = true;
                break;

            case 'empty':
            case 'nonEmpty':
                if (($this->empty && $ruleName == 'nonEmpty') || ($this->nonEmpty && $ruleName == 'empty')) {
                    throw new InvalidRuleException(
                        'Validation rules \'empty\' and \'nonEmpty\' cannot co-exist'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
                $this->{$ruleName} = true;
                break;

            default:
                $rule = $this->factory->ruleProvider->getRule($ruleName);
                if (!$rule) {
                    throw new InvalidRuleException(
                        'Validation rule-by-key[' . $ruleName . '] is not supported'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
                $candidate = new RuleSetRule($rule, true, (int) $index);
                // Illegal by-value?
                // Use Rule::name because may be renamed.
                if (isset(static::ILLEGAL_BY_VALUE[$rule->name])) {
                    throw new InvalidRuleException($this->candidateErrorMsg($candidate, ' is illegal'));
                }
                // Method cannot take arguments.
                if ($rule->paramsRequired) {
                    throw new InvalidRuleException(
                        $this->candidateErrorMsg($candidate, ' requires ' . $rule->paramsRequired . ' argument(s)')
                    );
                }
                // Dupe?
                // Use Rule::name because may be renamed.
                if (isset($this->ruleCandidates[$rule->name])) {
                    throw new InvalidRuleException(
                        $this->candidateErrorMsg($candidate, ' conflicts with rule-by-value of same name')
                    );
                }
                // Use Rule::name because may be renamed.
                $this->ruleCandidates[$rule->name] = $candidate;
        }
    }

    /**
     * Checks/prepares argument for enum/alternativeEnum.
     *
     * Removes null value and sets nullable instead.
     *
     * Bucket values must be scalar|null
     * @see Type::SCALAR_NULLABLE
     * or bool|int|string|null.
     * @see Type::EQUATABLE
     * Type definition of the 'enum' pattern rule decides which:
     * @see PatternRulesInterface::MINIMAL_PATTERN_RULES
     *
     * @param Rule $rule
     *      enum Rule.
     * @param mixed $argument
     *      Errs if not array.
     * @param string|null $actualRuleName
     *      If other than 'enum'.
     *
     * @return array
     *      Empty if only contained null bucket.
     *
     * @throws InvalidRuleException
     */
    protected function enum(Rule $rule, $argument, string $actualRuleName = null) : array
    {
        if (!$argument || !is_array($argument)) {
            throw new InvalidRuleException(
                'Validation \'' . ($actualRuleName ?? 'enum') . '\' type[' . Helper::getType($argument)
                . '] is not non-empty array' . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
            );
        }
        // Support definition as nested array, because enum used to require
        // (overly formalistic) that the allowed values array was nested;
        // since the allowed values array is the second argument to be passed
        // to the enum() method.
        if (is_array(reset($argument))) {
            $allowed_values = current($argument);
        }
        else {
            $allowed_values = $argument;
        }

        // Check once and for all that allowed values are scalar|null.
        $i = -1;
        $enum = [];
        foreach ($allowed_values as $value) {
            ++$i;
            if ($value === null) {
                $this->nullable = true;
            }
            elseif ($rule->type == Type::EQUATABLE) {
                if (is_scalar($value) && !is_float($value)) {
                    $enum[] = $value;
                }
                else {
                    throw new InvalidRuleException(
                        'Validation \'' . ($actualRuleName ?? 'enum') . '\' allowed values bucket[' . $i
                        . '] type[' . Helper::getType($value) . '] is not bool|int|string|null'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
            }
            else {
                // Type::SCALAR_NULLABLE
                if (is_scalar($value)) {
                    $enum[] = $value;
                }
                else {
                    throw new InvalidRuleException(
                        'Validation \'' . ($actualRuleName ?? 'enum') . '\' allowed values bucket[' . $i
                        . '] type[' . Helper::getType($value) . '] is not scalar or null'
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
            }
        }

        return $enum;
    }

    /**
     * Pseudo rule listing ValidationRuleSets of elements of object|array subject.
     *
     * @param object|array $argument
     *
     * @return TableElements
     *
     * @throws InvalidRuleException
     *      Arg $arguments not object|array.
     */
    protected function tableElements($argument) : TableElements
    {
        $class_table_elements = static::CLASS_TABLE_ELEMENTS;
        if (is_object($argument)) {
            if ($argument instanceof $class_table_elements) {
                /** @var TableElements $argument */
                return $argument;
            }
            else {
                /**
                 * new TableElements(
                 * @see TableElements::__construct()
                 */
                return new $class_table_elements($this->factory, $argument, $this->depth, $this->keyPath);
            }
        }
        elseif (is_array($argument)) {
            /**
             * new TableElements(
             * @see TableElements::__construct()
             */
            return new $class_table_elements($this->factory, (object) $argument, $this->depth, $this->keyPath);
        }
        throw new InvalidRuleException(
            'Validation \'tableElements\' type[' . Helper::getType($argument)
            . '] is not a object|array' . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
        );
    }

    /**
     * Pseudo rule representing every element of object|array subject.
     *
     * @param object|array $argument
     *
     * @return ListItems
     *
     * @throws InvalidRuleException
     *      Arg $arguments not object|array.
     */
    protected function listItems($argument) : ListItems
    {
        $class_list_items = static::CLASS_LIST_ITEMS;
        if (is_object($argument)) {
            if ($argument instanceof $class_list_items) {
                /** @var ListItems $argument */
                return $argument;
            }
            else {
                /**
                 * new ListItems(
                 * @see ListItems::__construct()
                 */
                return new $class_list_items($this->factory, $argument, $this->depth, $this->keyPath);
            }
        }
        elseif (is_array($argument)) {
            $class = static::CLASS_LIST_ITEMS;
            /**
             * new ListItems(
             * @see ListItems::__construct()
             */
            return new $class($this->factory, (object) $argument, $this->depth, $this->keyPath);
        }
        throw new InvalidRuleException(
            'Validation \'listItems\' type[' . Helper::getType($argument)
            . '] is not a object|array' . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
        );
    }

    /**
     * @param RuleSetRule $rule
     * @param string $message
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *      Arg $message empty.
     */
    protected function candidateErrorMsg(RuleSetRule $rule, string $message) : string
    {
        if ($message === '') {
            throw new \InvalidArgumentException('Arg $message cannot be empty.');
        }
        if ($rule->passedByValueAtIndex === null) {
            $msg = 'Validation rule-by-key[' . $rule->name . ']';
        } else {
            $msg = 'Validation rule-by-value[' . $rule->name
                . '] at numeric index[' . $rule->passedByValueAtIndex . ']';
        }
        if ($rule->renamedFrom) {
            $msg .= ' renamed from[' . $rule->renamedFrom . ']';
        }
        return $msg . $message . ', at (' . $this->depth . ') ' . $this->keyPath . '.';
    }
}
