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
use SimpleComplex\Validate\Helper\AbstractRule;
use SimpleComplex\Validate\Helper\Helper;
use SimpleComplex\Validate\RuleSet\ValidationRuleSet;
use SimpleComplex\Validate\RuleSet\TableElements;
use SimpleComplex\Validate\RuleSet\ListItems;

use SimpleComplex\Validate\Exception\OutOfRangeException;
use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\LogicException;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Creates a single orderly validation ruleset based on an input ruleset sketch.
 *
 * The input rules could be a more or less rough list, written in JSON or PHP.
 *
 * Recursive - the current ruleset returned could be a child or parent of other
 * rulesets.
 *
 * @internal  Meant to used by a ruleset factory.
 * @see RuleSetFactory::make()
 *
 * Design technicalities
 * ---------------------
 * Can only create a single ruleset, and holds state of that ruleset (until
 * fully created).
 * If the ruleset contains tableElements or listItems, those will be generated
 * by other generators (created by TableElements|ListItems).
 * @see tableElements()
 * @see TableElements::defineRulesByElements()
 * @see listItems()
 * @see ListItems::defineItemRules()
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
    public const RECURSION_LIMIT = 10;

    /**
     * Pseudo rules that cannot be passed by value.
     *
     * @see ruleByValue()
     * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
     *
     * @var mixed[]
     */
    public const ILLEGAL_BY_VALUE = [
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
    public const ALTERNATIVE_RULESET_ILLEGALS = [
        'alternativeRuleSet' => null,
    ];

    /**
     * Whether alternativeEnum[null] is accepted as a means of setting nullable,
     * disregarding whether 'enum' allows null.
     *
     * @see enum()
     *
     * @var bool
     */
    public const ALTERNATIVE_ENUM_SET_NULLABLE_ONLY = true;

    /**
     * @var string
     */
    protected const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * @var string
     */
    protected const CLASS_TABLE_ELEMENTS = TableElements::class;

    /**
     * @var string
     */
    protected const CLASS_LIST_ITEMS = ListItems::class;

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
     *
     * @throws OutOfRangeException
     *      Recursion limit exceeded.
     * @throws InvalidArgumentException
     */
    public function __construct(RuleSetFactory $factory, $rules, int $depth = 0, string $keyPath = 'root')
    {
        // Constructor deliberately takes all the needed parameters,
        // to make it clear that a generator isn't reusable.
        // Dependency injection must take place on factory level.

        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation ruleset definition at limit['
                . static::RECURSION_LIMIT . ']' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $this->factory = $factory;

        if (is_object($rules)) {
            if ($rules instanceof \ArrayAccess) {
                throw new InvalidArgumentException(
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
            throw new InvalidArgumentException(
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
        $rules = [];

        // At top for clarity.
        if ($this->optional) {
            $rules['optional'] = true;
        }
        if ($this->nullable) {
            $rules['nullable'] = true;
        }

        // Type-checking rules must be the first real rules of the ruleset.
        foreach ($this->typeRules as $rule) {
            $rules[$rule->name] = $rule->argument;
        }

        // Goes after type-checking, but don't require type-checking.
        if ($this->empty) {
            $rules['empty'] = true;
        }
        elseif ($this->nonEmpty) {
            $rules['nonEmpty'] = true;
        }

        // Non-type-checking rules must go after type-checking.
        foreach ($this->patternRules as $rule) {
            $rules[$rule->name] = $rule->argument;
        }

        // Pseudo rules go at bottom of the ruleset, for clarity only.
        foreach (['alternativeEnum', 'alternativeRuleSet', 'tableElements', 'listItems'] as $pseudoRule) {
            if ($this->{$pseudoRule}) {
                $rules[$pseudoRule] = $this->{$pseudoRule};
            }
        }

        $class_ruleset = static::CLASS_RULE_SET;
        /** @var ValidationRuleSet $ruleset */
        $ruleset = new $class_ruleset($rules);

        return $ruleset;
    }

    /**
     * Ensures that the ruleset will contain a type-checking method,
     * if necessary.
     *
     * Also checks that the resulting ruleset isn't effectively empty.
     *
     * @throws InvalidRuleException
     * @throws LogicException
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

        // tableElements|listItems must check non-empty,
        // and require loopable container.
        if ($this->tableElements || $this->listItems) {
            // The 'empty' rule is not compatible with tableElements|listItems.
            if ($this->empty) {
                throw new InvalidRuleException(
                    'Validation ruleset ' . (!$this->tableElements ? 'tableElements' : 'listItems')
                    . ' is not compatible with the empty() rule'
                    . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                );
            }

            // tableElements|listItems must require container.
            // Ideally iterable or loopable, but that would effectively
            // make tableElements|listItems incompatible with primitive class
            // those members are public, but doesn't implement \Traversable.
            $method = $this->factory->ruleProvider->patternRuleToTypeRule(Type::CONTAINER);
            if (!$method) {
                throw new LogicException(
                    'Rule provider ' . get_class($this->factory->ruleProvider)
                    . ' has no type rule matching type CONTAINER.'
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
            throw new LogicException(__CLASS__ . '::$patternRules cannot be empty at this stage.');
        }
        $patternRule = reset($this->patternRules);
        $method = $this->factory->ruleProvider->patternRuleToTypeRule(null, $patternRule->name);
        if (!$method) {
            throw new LogicException(
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
                // Clone to overwrite name property without affecting the rule
                // provider's Rule (getRule() probably caches Rule objects).
                $rule = clone $this->factory->ruleProvider->getRule('enum');
                $rule->name = 'alternativeEnum';
                $enum = $this->enum($rule, $argument);
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
     * Bucket values must be scalar (or null)
     * @see Type::SCALAR
     * @see Type::SCALAR_NULL
     * or bool|int|string (or null).
     * @see Type::EQUATABLE
     * @see Type::EQUATABLE_NULL
     * Type definition of the 'enum' pattern rule decides which:
     * @see PatternRulesInterface::MINIMAL_PATTERN_RULES
     *
     * @param AbstractRule $rule
     *      enum Rule.
     * @param mixed $argument
     *      Errs if not array.
     *
     * @return array
     *      Empty if only contained null bucket.
     *
     * @throws InvalidRuleException
     */
    protected function enum(AbstractRule $rule, $argument) : array
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

        $pass_null = $pass_float = false;
        switch ($rule->type) {
            case Type::EQUATABLE:
                break;
            case Type::EQUATABLE_NULL:
                $pass_null = true;
                break;
            case Type::SCALAR:
                $pass_float = true;
                break;
            default:
                /** @see Type::SCALAR_NULL */
                $pass_null = $pass_float = true;
        }

        // Check once and for all that allowed values are valid.
        $i = -1;
        $enum = [];
        foreach ($allowed_values as $value) {
            ++$i;
            if ($value === null) {
                $size = count($allowed_values);
                if ($pass_null
                    // alternativeEnum[null] is still supported as a means
                    // of allowing null.
                    || ($size == 1 && static::ALTERNATIVE_ENUM_SET_NULLABLE_ONLY && $rule->name == 'alternativeEnum')
                ) {
                    $this->nullable = true;
                    if ($size == 1) {
                        return [];
                    }
                }
                else {
                    // EQUATABLE|SCALAR.
                    throw new InvalidRuleException(
                        'Validation rule \'' . $rule->name . '\' allowed values bucket[' . $i . '] type[null] is not '
                        . Type::typeMessage($rule->type)
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
            }
            elseif (is_float($value)) {
                if ($pass_float) {
                    $enum[] = $value;
                }
                else {
                    // EQUATABLE|EQUATABLE_NULL.
                    throw new InvalidRuleException(
                        'Validation rule \'' . $rule->name . '\' allowed values bucket[' . $i . '] type[float] is not '
                        . Type::typeMessage($rule->type)
                        . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                    );
                }
            }
            elseif (is_scalar($value)) {
                $enum[] = $value;
            }
            else {
                throw new InvalidRuleException(
                    'Validation rule \'' . $rule->name . '\' allowed values bucket[' . $i
                    . '] type[' . Helper::getType($value) . '] is not ' . Type::typeMessage($rule->type)
                    . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
                );
            }
        }

        if (!$enum) {
            throw new InvalidRuleException(
                'Validation rule \'' . $rule->name . '\' allowed values array is empty'
                . ', at (' . $this->depth . ') ' . $this->keyPath . '.'
            );
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
     * @throws InvalidArgumentException
     *      Arg $message empty.
     */
    protected function candidateErrorMsg(RuleSetRule $rule, string $message) : string
    {
        if ($message === '') {
            throw new InvalidArgumentException('Arg $message cannot be empty.');
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
