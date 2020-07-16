<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Exception\OutOfRangeException;

/**
 * Validation rule set.
 *
 * Checks integrity of non-provider rules and converts child rule sets
 * (tableElements.rulesByElements, listItems.itemRules) to ValidationRuleSets.
 *
 * Only checks integrity of arguments for the provider rule enum().
 * For all other provider rules the arguments get checked run-time
 * by the rule methods called.
 *
 *
 * @see Validate::challenge()
 *
 * @see simple_complex_validate_test_cli()
 *      For example of use.
 *
 * @property bool|array *
 *      Provider rules are set/declared as instance vars dynamically.
 *
 * @property boolean|undefined $optional
 *      Flags that the object|array subject element do not have to exist.
 *
 * @property boolean|undefined $allowNull
 *      Flags that the element is allowed to be null.
 *      Null is not the same as non-existent (optional).
 *
 * @property array|undefined $enum
 *      List of valid values. Bucket values must be scalar|null.
 *
 * @property array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *      Bucket values must be scalar|null.
 *
 * @property ValidationRuleSet|undefined $alternativeRuleSet
 *      Alternative rule set used if subject doesn't comply with
 *      other - typically type checking - rules and/or alternativeEnum.
 *
 * @property TableElements|undefined $tableElements {
 *      @var ValidationRuleSet[] $rulesByElements
 *          ValidationRuleSet by element key.
 *      @var string[] $keys
 *          Keys of rulesByElements.
 *      @var bool|undefined $exclusive
 *          Subject object|array must only contain keys defined
 *          by rulesByElements.
 *      @var array|undefined $whitelist
 *          Subject object|array must only contain these keys,
 *          apart from the keys defined by $rulesByElements.
 *      @var array|undefined $blacklist
 *          Subject object|array must not contain these keys,
 *          apart from the keys defined by $rulesByElements.
 * }
 *      Rule listing ValidationRuleSets of elements of object|array subject.
 *
 *      Flags/lists exclusive, whitelist and blacklist are mutually exclusive.
 *      If subject is \ArrayAccess without a getArrayCopy() method then that
 *      will count as validation failure, because validation not possible.
 *
 *      tableElements combined with listItems is allowed.
 *      If tableElements pass then listItems will be ignored.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 * @property ListItems|undefined $listItems {
 *      @var ValidationRuleSet|object|array $itemRules
 *          Rule set which will be applied on every item.
 *      @var int|undefined $minOccur
 *      @var int|undefined $maxOccur
 * }
 *      Rule representing every element of object|array subject.
 *
 *      listItems combined with tableElements is allowed.
 *      If tableElements pass then listItems will be ignored.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *
 * Design considerations - why no \Traversable or \Iterator?
 * ---------------------------------------------------------
 * The only benefits would be i. that possibly undefined properties could be
 * assessed directly (without isset()) and ii. that the (at)property
 * documentation would be formally correct (not ...|undefined).
 * The downside would be deteriorated performance.
 * The class is primarily aimed at ValidateAgainstRuleSet use. Convenience
 * of access for other purposes is not a priority.
 *
 *
 * @package SimpleComplex\Validate
 */
class ValidationRuleSet
{
    /**
     * Recursion emergency brake.
     *
     * Ideally the depth of a rule set describing objects/arrays having nested
     * objects/arrays should limit recursion, naturally/orderly.
     * But circular references within the rule set - or a programmatic error
     * in this library - could (without this hardcoded limit) result in
     * perpetual recursion.
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
    const CLASS_TABLE_ELEMENTS = TableElements::class;

    /**
     * @var string
     */
    const CLASS_LIST_ITEMS = ListItems::class;

    /**
     * Validation rule set.
     *
     * Checks validity of arg rules, recursively.
     *
     * Ensures that the rule set contains at least one type checking rule
     * (using 'string' as fallback),
     * and places that rule as first in the set.
     *
     * Converts descendant rule sets to ValidationRuleSet.
     *
     * @param object|array $rules
     *      ArrayAccess is not supported.
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     *
     * @throws \TypeError
     *      Arg $rules is not object|array.
     * @throws \InvalidArgumentException
     *      Arg $rules is \ArrayAccess.
     * @throws InvalidRuleException
     */
    public function __construct($rules, $ruleProvider, int $depth = 0, string $keyPath = 'root')
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation rule set definition at limit['
                . static::RECURSION_LIMIT . ']' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        if (is_object($rules)) {
            if ($rules instanceof \ArrayAccess) {
                throw new \InvalidArgumentException(
                    'Arg rules type[' . Helper::getType($rules)
                    . '] \ArrayAccess is not supported' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $o_rules = $rules;
        }
        elseif (is_array($rules)) {
            $o_rules = (object) $rules;
        }
        else {
            throw new \TypeError(
                'Arg rules type[' . Helper::getType($rules)
                . '] is not object|array' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $rules_supported = $ruleProvider->getRuleMethods();
        $type_rules_supported = $ruleProvider->getTypeMethods();
        // List of provider rules taking arguments.
        $parameter_rules = $ruleProvider->getParameterMethods();
        $rules_renamed = $ruleProvider->getRulesRenamed();

        $skip_rules = [];

        // Ensure that there's a type checking method,
        // and that it goes at the top; before rules that don't type check.
        $type_rule_found = false;
        foreach ($type_rules_supported as $rule) {
            if (!empty($o_rules->{$rule})) {
                $type_rule_found = true;
                $skip_rules[] = $rule;
                // The class rule is the only type-checking rule that allows
                // another parameter than subject.
                if ($rule == 'class') {
                    // Support string as well as [string].
                    if (is_array($o_rules->class)) {
                        $class = reset($o_rules->class);
                        if (!$class || !is_string($class)) {
                            throw new InvalidRuleException(
                                'Validation rule \'class\' array[0] type[' . Helper::getType($class)
                                . '] is not non-empty string' . ', at (' . $depth . ') ' . $keyPath . '.'
                            );
                        }
                    }
                    else {
                        $class = $o_rules->class;
                        if (!$class || !is_string($class)) {
                            throw new InvalidRuleException(
                                'Validation rule \'class\' type[' . Helper::getType($class)
                                . '] is not non-empty string' . ', at (' . $depth . ') ' . $keyPath . '.'
                            );
                        }
                    }
                    // Declare dynamically.
                    $this->{$rule} = $class;
                }
                else {
                    // Declare dynamically.
                    $this->{$rule} = true;
                }
            }
        }
        if (!$type_rule_found) {
            $rule = $this->inferTypeCheckingRule($o_rules, $ruleProvider);
            // Declare dynamically.
            $this->{$rule} = true;
        }
        unset($type_rule_found);

        foreach ($o_rules as $rule => $argument) {
            if (ctype_digit('' . $rule)) {
                // Simple non-parameter rule declared by value instead of key.
                if (is_string($argument) && $argument) {
                    if (property_exists($o_rules, $argument)) {
                        throw new InvalidRuleException(
                            'Validation rule by value[' . $argument . '] at numeric index[' . $rule
                            . '] conflicts with rule by key of same name' . ', at (' . $depth . ') ' . $keyPath . '.'
                        );
                    }
                    if ($argument == 'optional' || $argument == 'allowNull') {
                        $this->{$argument} = true;
                    }
                    elseif ($skip_rules && in_array($argument, $skip_rules)) {
                        continue;
                    }
                    else {
                        $method = $argument;
                        if (!in_array($method, $rules_supported)) {
                            if (isset($rules_renamed[$method])) {
                                $method = $rules_renamed[$method];
                            }
                            else {
                                throw new InvalidRuleException(
                                    'Validation rule by value[' . $argument . '] at numeric index[' . $rule
                                    . '] is not supported' . ', at (' . $depth . ') ' . $keyPath . '.'
                                );
                            }
                        }
                        if (!empty($parameter_rules[$method])) {
                            throw new InvalidRuleException(
                                'Validation rule by value[' . $argument . '] at numeric index[' . $rule
                                . '] requires arguments' . ', at (' . $depth . ') ' . $keyPath . '.'
                            );
                        }
                        // Declare dynamically.
                        $this->{$method} = true;
                    }
                    continue;
                }
                throw new InvalidRuleException(
                    'Validation rule set key[' . $rule . '] value type[' . Helper::getType($argument)
                    . '] does not make sense' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }

            if ($skip_rules && in_array($rule, $skip_rules)) {
                continue;
            }

            switch ($rule) {
                case 'optional':
                case 'allowNull':
                    // Allow falsy, but then don't set.
                    if ($argument) {
                        // Declare dynamically.
                        $this->{$rule} = true;
                    }
                    break;

                case 'enum':
                case 'alternativeEnum':
                    // Declare dynamically.
                    $this->{$rule} = $this->enum($rule, $argument, $depth, $keyPath);
                    break;

                case 'alternativeRuleSet':
                    if ($argument instanceof ValidationRuleSet) {
                        // Declare dynamically.
                        $this->alternativeRuleSet = $argument;
                    }
                    else {
                        // Declare dynamically.
                        // new ValidationRuleSet(.
                        $this->alternativeRuleSet = new static(
                            $argument, $ruleProvider, $depth + 1, $keyPath . '(alternativeRuleSet)'
                        );
                    }
                    // alternativeRuleSet illegal children.
                    foreach (['alternativeRuleSet', 'tableElements', 'listItems'] as $illegal_child) {
                        if (isset($this->alternativeRuleSet->{$illegal_child})) {
                            throw new InvalidRuleException(
                                'Validation \'alternativeRuleSet\' is not allowed to contain \'' . $illegal_child . '\''
                                . ', at (' . $depth . ') ' . $keyPath . '.'
                            );
                        }
                    }
                    break;

                case 'tableElements':
                    // Declare dynamically.
                    $this->tableElements = $this->tableElements($argument, $ruleProvider, $depth, $keyPath);
                    break;

                case 'listItems':
                    // Declare dynamically.
                    $this->listItems = $this->listItems($argument, $ruleProvider, $depth, $keyPath);
                    break;

                default:
                    $method = $rule;
                    // Check rule method existance.
                    if (!in_array($rule, $rules_supported)) {
                        if (isset($rules_renamed[$rule])) {
                            $method = $rules_renamed[$rule];
                            if ($skip_rules && in_array($method, $skip_rules)) {
                                break;
                            }
                        }
                        else {
                            throw new InvalidRuleException(
                                'Unknown validation rule[' . $rule . ']' . ', at (' . $depth . ') ' . $keyPath . '.'
                            );
                        }
                    }

                    // Rule method accepts more arguments than subject self.
                    if (isset($parameter_rules[$method])) {
                        // Rule method _requires_ argument(s).
                        if ($parameter_rules[$method]) {
                            // Rule value must be array.
                            if (!$argument || !is_array($argument)) {
                                throw new InvalidRuleException(
                                    'Validation rule[' . $method . '] requires more arguments than subject self'
                                    . ', and rule value type[' . Helper::getType($argument)
                                    . '] is not non-empty array' . ', at (' . $depth . ') ' . $keyPath . '.'
                                );
                            }
                            // Declare dynamically.
                            $this->{$method} = $argument;
                        }
                        // Rule method _allows_ argument(s).
                        else {
                            if ($argument === true) {
                                // Rule method accepts argument(s), but
                                // none given; true as simple 'on' flag.
                                // Declare dynamically.
                                $this->{$method} = true;
                            }
                            elseif (!$argument || !is_array($argument)) {
                                // Rule method accepts argument(s);
                                // if not true it must be non-empty array.
                                throw new InvalidRuleException(
                                    'Validation rule[' . $method . '] accepts more arguments than subject'
                                    . ', but value type[' . Helper::getType($argument)
                                    . '] is neither boolean true (simple \'on\' flag)'
                                    . ', nor non-empty array (list of secondary argument(s))'
                                    . ', at (' . $depth . ') ' . $keyPath . '.'
                                );
                            }
                            else {
                                // Declare dynamically.
                                $this->{$method} = $argument;
                            }
                        }
                    }
                    // Rule doesn't accept arguments;
                    // value must be boolean true.
                    elseif ($argument === true) {
                        // Declare dynamically.
                        $this->{$method} = true;
                    }
                    else {
                        // Rule method doesn't accept argument(s);
                        // value should be boolean true.
                        throw new InvalidRuleException(
                            'Validation rule[' . $method . '] doesn\'t accept more arguments than subject self'
                            . ', thus value type[' . Helper::getType($argument)
                            . '] makes no sense, value only allowed to be boolean true'
                            . ', at (' . $depth . ') ' . $keyPath . '.'
                        );
                    }
            }
        }

        // enum must be scalar|null, not container.
        if (isset($this->enum)) {
            foreach (['tableElements', 'listItems'] as $illegal_sibling) {
                if (isset($this->{$illegal_sibling})) {
                    throw new InvalidRuleException(
                        'Validation rules \'enum\' and \'' . $illegal_sibling . '\' are incompatible'
                        . ' because a passed enum is scalar|null (not a container)'
                        . ', at (' . $depth . ') ' . $keyPath . '.'
                    );
                }
            }
        }
    }

    /**
     * Establishes a type checking rule method that matches
     * other rules of a ruleset.
     *
     * Defaults to string.
     *
     * @see RuleProviderInterface::getTypeMethods()
     *
     * @param object $ruleSet
     * @param RuleProviderInterface $ruleProvider
     *
     * @return string
     */
    public function inferTypeCheckingRule(object $ruleSet, RuleProviderInterface $ruleProvider) : string
    {
        if (isset($ruleSet->tableElements) || isset($ruleSet->listItems)) {
            /**
             * @see RuleProviderInterface::loopable()
             */
            return 'loopable';
        }
        elseif (in_array('digital', $ruleProvider->getTypeMethods())
            && (isset($ruleSet->bit32) || isset($ruleSet->bit64))
        ) {
            /**
             * @see Validate::digital()
             */
            return 'digital';
        }
        elseif (in_array('numeric', $ruleProvider->getTypeMethods())
            && (isset($ruleSet->positive) || isset($ruleSet->nonNegative) || isset($ruleSet->negative)
                || isset($ruleSet->min) || isset($ruleSet->max) || isset($ruleSet->range)
            )
        ) {
            /**
             * @see Validate::numeric()
             */
            return 'numeric';
        }
        /**
         * @see RuleProviderInterface::string()
         */
        return 'string';
    }

    /**
     * Pseudo rule listing valid values.
     *
     * Bucket values must be scalar|null.
     *
     * @see ValidationRuleSet::$enum
     * @see ValidationRuleSet::$alternativeEnum
     *
     * @param string $ruleName
     * @param array $argument
     * @param int $depth
     * @param string $keyPath
     *
     * @return array
     */
    protected function enum(string $ruleName, $argument, int $depth, string $keyPath) : array
    {
        if (!$argument || !is_array($argument)) {
            throw new InvalidRuleException(
                'Validation \'' . $ruleName . '\' type[' . Helper::getType($argument)
                . '] is not non-empty array' . ', at (' . $depth . ') ' . $keyPath . '.'
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
        foreach ($allowed_values as $value) {
            ++$i;
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidRuleException(
                    'Validation \'' . $ruleName . '\' allowed values bucket[' . $i
                    . '] type[' . Helper::getType($value)
                    . '] is not scalar or null' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
        }

        return $allowed_values;
    }

    /**
     * Pseudo rule listing ValidationRuleSets of elements of object|array subject.
     *
     * @param object|array $argument
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     *
     * @return TableElements
     *
     * @throws InvalidRuleException
     *      Arg $arguments not object|array.
     */
    protected function tableElements(
        $argument,
        RuleProviderInterface $ruleProvider,
        int $depth,
        string $keyPath
    ) : TableElements {
        if (is_object($argument)) {
            if ($argument instanceof TableElements) {
                return $argument;
            }
            else {
                $class = static::CLASS_TABLE_ELEMENTS;
                /**
                 * new TableElements(
                 * @see TableElements::__construct()
                 */
                return new $class($argument, $ruleProvider, $depth, $keyPath);
            }
        }
        elseif (is_array($argument)) {
            $class = static::CLASS_TABLE_ELEMENTS;
            /**
             * new TableElements(
             * @see TableElements::__construct()
             */
            return new $class((object) $argument, $ruleProvider, $depth, $keyPath);
        }
        throw new InvalidRuleException(
            'Validation \'tableElements\' type[' . Helper::getType($argument)
            . '] is not a object|array' . ', at (' . $depth . ') ' . $keyPath . '.'
        );
    }

    /**
     * Pseudo rule representing every element of object|array subject.
     *
     * @param object|array $argument
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     *
     * @return ListItems
     *
     * @throws InvalidRuleException
     *      Arg $arguments not object|array.
     */
    protected function listItems(
        $argument,
        RuleProviderInterface $ruleProvider,
        int $depth,
        string $keyPath
    ) : ListItems {
        if (is_object($argument)) {
            if ($argument instanceof ListItems) {
                return $argument;
            }
            else {
                $class = static::CLASS_LIST_ITEMS;
                /**
                 * new ListItems(
                 * @see ListItems::__construct()
                 */
                return new $class($argument, $ruleProvider, $depth, $keyPath);
            }
        }
        elseif (is_array($argument)) {
            $class = static::CLASS_LIST_ITEMS;
            /**
             * new ListItems(
             * @see ListItems::__construct()
             */
            return new $class((object) $argument, $ruleProvider, $depth, $keyPath);
        }
        throw new InvalidRuleException(
            'Validation \'listItems\' type[' . Helper::getType($argument)
            . '] is not a object|array' . ', at (' . $depth . ') ' . $keyPath . '.'
        );
    }
}
