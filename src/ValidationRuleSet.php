<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Dependency;
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
 *
 * @property bool|array *
 *      Provider rules are set/declared as instance vars dynamically.
 *
 *
 * @property boolean|undefined $optional
 *      Flags that the array|object subject element do not have to exist.
 *
 *      Ignored if this ruleSet is root; only valid for sub element.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @property boolean|undefined $allowNull
 *      Flags that the element is allowed to be null.
 *      Null is not the same as non-existent (optional).
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @property array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *
 *      Bucket values must be scalar|null.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 * @property ValidationRuleSet|undefined $alternativeRuleSet
 *      Alternative rule set used if subject doesn't comply with
 *      other - typically type checking - rules and/or alternativeEnum.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 * @property object|undefined $tableElements {
 *      @var object $rulesByElements
 *          ValidationRuleSet by element key.
 *      @var bool|undefined $exclusive
 *          Subject array|object must not contain any other keys
 *          than those defined by $rulesByElements.
 *      @var array|undefined $whitelist
 *          Subject array|object must only contain these keys,
 *          apart from the keys defined by $rulesByElements.
 *      @var array|undefined $blacklist
 *          Subject array|object must not contain these keys.
 * }
 *      Rule listing ValidationRuleSets of elements of array|object subject.
 *
 *      If no type checking rule then container() will be used.
 *
 *      Flags/lists exclusive, whitelist and blacklist are mutually exclusive.
 *      If subject is \ArrayAccess without a getArrayCopy() method then that
 *      will count as validation failure, because validation no possible.
 *
 *      tableElements combined with listItems is allowed.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *      Only declared if relevant, otherwise undefined.
 *
 * @property object|undefined $listItems {
 *      @var ValidationRuleSet|array|object $itemRules
 *          Rule set which will be applied on every item.
 *      @var int|undefined $minOccur
 *      @var int|undefined $maxOccur
 * }
 *      Rule representing every element of array|object subject.
 *
 *      If no type checking rule then container() will be used.
 *
 *      listItems combined with tableElements is allowed.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * Design considerations - why no \Traversable or \Iterator?
 * ---------------------------------------------------------
 * The only benefits would be i. that possibly undefined properties could be
 * assessed directly (without isset()) and ii. that the (at)property
 * documentation would be formally correct (not ...|undefined).
 * The downside would be deteriorated performance.
 * The class is primarily aimed at ValidateAgainstRuleSet' use. Convenience
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
     * @var string[]
     */
    const TABLE_ELEMENTS_ALLOWED_KEYS = [
        'rulesByElements', 'exclusive', 'whitelist', 'blacklist',
    ];

    /**
     * @var string[]
     */
    const LIST_ITEMS_ALLOWED_KEYS = [
        'itemRules', 'minOccur', 'maxOccur',
    ];

    /**
     * New rule name by old rule name.
     *
     * @var string[]
     */
    const RULES_RENAMED = [
        'dateIso8601Local' => 'dateISO8601Local',
        'dateTimeIso8601' => 'dateTimeISO8601',
    ];

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
     * @see RuleProviderInfo
     *
     * @param array|object $rules
     *      ArrayAccess is not supported.
     * @param RuleProviderInterface|RuleProviderInfo|null $ruleProvider
     * @param int $depth
     *
     * @throws InvalidRuleException
     */
    public function __construct($rules = [], $ruleProvider = null, $depth = 0)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation rule set definition at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        /**
         * Constructor has no required parameters, to allow casting to it.
         * @see Utils::cast()
         */
        if (!$rules) {
            return;
        }
        $is_object = false;
        if (!is_array($rules)) {
            if (is_object($rules)) {
                if ($rules instanceof \ArrayAccess) {
                    throw new \InvalidArgumentException(
                        'Arg rules at depth[' . $depth . '] type[' . Utils::getType($rules)
                        . '] \ArrayAccess is not supported.'
                    );
                }
                $is_object = true;
            }
            else {
                throw new \InvalidArgumentException(
                    'Arg rules at depth[' . $depth . '] type[' . Utils::getType($rules) . '] is not array|object.'
                );
            }
        }

        if (!$ruleProvider) {
            $provider_info = new RuleProviderInfo();
        }
        elseif ($ruleProvider instanceof RuleProviderInfo) {
            $provider_info = $ruleProvider;
        }
        elseif ($ruleProvider instanceof RuleProviderInterface) {
            $provider_info = new RuleProviderInfo($ruleProvider);
        }
        else {
            throw new \InvalidArgumentException(
                'Arg ruleProvider at depth[' . $depth . '] type[' . Utils::getType($ruleProvider)
                . '] is not RuleProviderInterface|RuleProviderInfo|null.'
            );
        }

        $rules_found = [];

        // Ensure that there's a type checking method,
        // and that it goes at the top; before rules that don't type check.
        // NB: Not reliable if $rules is array having numerically indexed rules
        // (bucket value name of rule).
        $rule_keys = array_keys(!$is_object ? $rules : get_object_vars($rules));
        $type_rules_found = array_intersect($provider_info->typeMethods, $rule_keys);
        $skip_type_rule = null;
        if (!$type_rules_found) {
            // Default to string unless object|array; then container.
            if (in_array('tableElements', $rule_keys, true) || in_array('listItems', $rule_keys, true)) {
                // Declare dynamically.
                $this->container = true;
            }
            else {
                // Declare dynamically.
                $this->string = true;
            }
        }
        else {
            $type_rule_name = reset($type_rules_found);
            // No type rule is allowed to take other arguments than the subject,
            // except the 'class' rule.
            if ($type_rule_name == 'class') {
                // Insert 'object' rule instead of moving 'class' rule, because
                // we want the 'class' rule to be checked (like any other rule).
                // Declare dynamically.
                $this->object = true;
                $skip_type_rule = 'object';
            }
            else {
                // Insert type checking rule as first rule.
                if (!$is_object) {
                    $type_rule_value = $rules[$type_rule_name];
                } else {
                    $type_rule_value = $rules->{$type_rule_name};
                }
                // Declare dynamically.
                $this->{$type_rule_name} = $type_rule_value;
                $skip_type_rule = $type_rule_name;
            }
            unset($type_rules_found, $type_rule_name, $type_rule_value);
        }

        // List of provider rules taking arguments.
        $provider_parameter_methods = null;

        foreach ($rules as $ruleKey => &$ruleValue) {
            unset($args);
            if (ctype_digit('' . $ruleKey)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $ruleValue;
                $args = true;
                // The only non-provider rules supported are 'optional' and 'allowNull';
                // the others cannot be boolean.
                // And only provider rules having no parameters are legal here.
                switch ($rule) {
                    case 'optional':
                    case 'allowNull':
                        break;
                    case 'enum':
                        throw new InvalidRuleException(
                            'Provider validation rule[' . $rule . '] at depth[' .  $depth . ']'
                            . ' cannot be defined via numeric key and bucket value,'
                            . ' because cannot be (simple) boolean true,'
                            . ' must be array (of secondary rule method arguments).'
                        );
                    default:
                        if (in_array($rule, ValidateAgainstRuleSet::NON_PROVIDER_RULES)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[' . $rule . '] at depth[' .  $depth . ']'
                                . ' cannot be defined via numeric key and bucket value,'
                                . ' because cannot be (simple) boolean true,'
                                . ' must be non-scalar.'
                            );
                        }
                        if (!in_array($rule, $provider_info->ruleMethods)) {
                            if (isset(static::RULES_RENAMED[$rule])) {
                                $rule = static::RULES_RENAMED[$rule];
                            }
                            else {
                                throw new InvalidRuleException(
                                    'Non-existent validation rule[' . $rule . '] at depth[' .  $depth . '],'
                                    . ' was attempted to be defined via numeric key and bucket value.'
                                );
                            }
                        }
                        // Could check whether the rule method has secondary paramaters, like
                        //if ((new \ReflectionMethod($ruleProvider, $rule))->getNumberOfParameters() > 1) {
                        //    throw new InvalidRuleException('... must be array (of secondary rule method arguments).');
                        //}
                        // but doesn't know rule provider instance (nor class) here.
                }
            } else {
                // Bucket key is name of the rule,
                // value is arguments for the rule method.
                $rule = $ruleKey;
                $args = $ruleValue;
            }

            // That rule is already set.
            if ($skip_type_rule && $rule == $skip_type_rule) {
                continue;
            }

            switch ($rule) {
                case 'optional':
                    if ($depth && $args) {
                        // Declare dynamically.
                        $this->optional = true;
                    }
                    break;
                case 'allowNull':
                    // Declare dynamically.
                    $this->allowNull = true;
                    break;

                case 'alternativeEnum':
                    if (!$args || !is_array($args)) {
                        throw new InvalidRuleException(
                            'Non-provider validation rule[alternativeEnum] at depth[' .  $depth
                            . '] type[' . Utils::getType($args) . '] is not non-empty array.'
                        );
                    }
                    // Allow defining alternativeEnum as nested array, because
                    // easy to confuse with the format for enum.
                    // enum formally requires to be nested, since
                    // the allowed values array is second argument
                    // to be passed to the enum() method.
                    if (is_array(reset($args))) {
                        $allowed_values = current($args);
                    } else {
                        $allowed_values =& $args;
                    }
                    // Check once and for all that allowed values are scalar|null.
                    $i = -1;
                    foreach ($allowed_values as $allowed) {
                        ++$i;
                        if ($allowed !== null && !is_scalar($allowed)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[alternativeEnum] at depth[' .  $depth
                                . '] allowed values bucket[' . $i . '] type[' . Utils::getType($allowed)
                                . '] is not scalar or null.'
                            );
                        }
                    }
                    // Declare dynamically.
                    $this->alternativeEnum =& $allowed_values;
                    unset($allowed_values, $allowed);
                    break;

                case 'alternativeRuleSet':
                    if ($args instanceof ValidationRuleSet) {
                        // Do not allow alternativeRuleSet to have alternativeRuleSet.
                        if (!empty($args->alternativeRuleSet)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[alternativeRuleSet] at depth[' .  $depth
                                . '] is not allowed to have alternativeRuleSet by itself.'
                            );
                        }
                        $this->alternativeRuleSet = $args;
                    }
                    else {
                        // new ValidationRuleSet(.
                        $this->alternativeRuleSet = new static($args, $provider_info, $depth + 1);
                    }
                    break;

                case 'tableElements':
                case 'listItems':
                    // Use $ruleValue directly; these rules cannot be set
                    // as value by numeric key.
                    unset($args);
                    if (is_array($ruleValue)) {
                        $ruleValue = (object) $ruleValue;
                    } elseif (!is_object($ruleValue)) {
                        $msg = 'Non-provider validation rule[' . $rule . '] at depth[' .  $depth
                            . '] type[' . Utils::getType($ruleValue) . '] is not a array|object.';
                        $container = Dependency::container();
                        if ($container->has('logger')) {
                            if ($container->has('inspect')) {
                                $inspection = $container->get('inspect')->variable($rules);
                            } else {
                                $inspection = 'Keys['
                                    . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
                            }
                            $container->get('logger')->warning($msg . "\n" . $inspection);
                        }
                        throw new InvalidRuleException($msg);
                    }

                    if ($rule == 'tableElements') {
                        // Fix obvious spelling errors.
                        if (isset($ruleValue->whiteList)) {
                            $ruleValue->whitelist = $ruleValue->whiteList;
                            unset($ruleValue->whiteList);
                        }
                        if (isset($ruleValue->blackList)) {
                            $ruleValue->blacklist = $ruleValue->blackList;
                            unset($ruleValue->blackList);
                        }
                        // Allow only specific buckets.
                        if (array_diff(
                            $prop_keys = array_keys(get_object_vars($ruleValue)),
                            static::TABLE_ELEMENTS_ALLOWED_KEYS
                        )) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                                . ' can only contain keys[' . join(', ', static::TABLE_ELEMENTS_ALLOWED_KEYS)
                                . '], saw[' . join(', ', $prop_keys) . '].'
                            );
                        }
                        unset($prop_keys);
                        // exclusive.
                        $has_lists = [];
                        if (isset($ruleValue->exclusive)) {
                            if (!is_bool($ruleValue->exclusive)) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                                    . ' bucket \'exclusive\' type[' . Utils::getType($ruleValue->exclusive)
                                    . '] is not boolean.'
                                );
                            } elseif ($ruleValue->exclusive) {
                                $has_lists[] = 'exclusive';
                            }
                        }
                        // whitelist|blacklist must be array, but allowed empty.
                        $list_keys = array('whitelist' , 'blacklist');
                        foreach ($list_keys as $list_key) {
                            if (isset($ruleValue->{$list_key})) {
                                if (!is_array($ruleValue->{$list_key})) {
                                    throw new InvalidRuleException(
                                        'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                                        . ' bucket \'' . $list_key . '\' type['
                                        . Utils::getType($ruleValue->{$list_key}) . '] is not array.'
                                    );
                                } elseif ($ruleValue->{$list_key}) {
                                    $has_lists[] = $list_key;
                                }
                            }
                        }
                        unset($list_keys, $list_key);
                        // exclusive|whitelist|blacklist are mutually exclusive.
                        if (count($has_lists) > 1) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                                . ' has more than one mutually exclusive buckets, saw[' . join(', ', $has_lists) . '].'
                            );
                        }
                        unset($has_lists);
                        // rulesByElements.
                        if (!isset($ruleValue->rulesByElements)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                                . ' misses array|object \'rulesByElements\' bucket.'
                            );
                        }
                        if (is_array($ruleValue->rulesByElements)) {
                            $ruleValue->rulesByElements = (object) $ruleValue->rulesByElements;
                        } elseif (!is_object($ruleValue->rulesByElements)) {
                            $msg = 'Non-provider validation rule[' . $rule . '] at depth[' .  $depth . ']'
                                . ' bucket \'rulesByElements\' type[' . Utils::getType($ruleValue->rulesByElements)
                                . '] is not a array|object.';
                            $container = Dependency::container();
                            if ($container->has('logger')) {
                                if ($container->has('inspect')) {
                                    $inspection = $container->get('inspect')->variable($rules);
                                } else {
                                    $inspection = 'Keys['
                                        . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
                                }
                                $container->get('logger')->warning($msg . "\n" . $inspection);
                            }
                            throw new InvalidRuleException($msg);
                        }
                        try {
                            $index = -1;
                            foreach ($ruleValue->rulesByElements as $elementName => &$subRuleSet) {
                                $name = $elementName;
                                ++$index;
                                if (!($subRuleSet instanceof ValidationRuleSet)) {
                                    if (is_array($subRuleSet) || is_object($subRuleSet)) {
                                        // new ValidationRuleSet(.
                                        $subRuleSet = new static(
                                            $subRuleSet, $provider_info, $depth + 1
                                        );
                                    } else {
                                        throw new InvalidRuleException(
                                            'Element rule set type[' . Utils::getType($subRuleSet)
                                            . '] is not ValidationRuleSet|array|object.'
                                        );
                                    }
                                }
                            }
                            // Iteration ref.
                            unset($subRuleSet);
                        } catch (\Throwable $xc) {
                            $msg = 'Non-provider validation rule[tableElements] at depth[' .  $depth
                                . '] element index[' . $index . '] name[' . $name
                                . '] is not a valid rule set, reason:';
                            $container = Dependency::container();
                            if ($container->has('logger')) {
                                if ($container->has('inspect')) {
                                    $inspection = 'Current rule set:'
                                        . "\n" . $container->get('inspect')->variable($rules)->toString(false);
                                } else {
                                    $inspection = 'Current rule set keys['
                                        . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
                                }
                                $container->get('logger')->warning(
                                    $msg . "\n" . $xc->getMessage() . "\n" . $inspection
                                );
                            }
                            throw new InvalidRuleException($msg . ' ' . $xc->getMessage());
                        }
                        if ($index == -1) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[tableElements] at depth[' .  $depth . '] is empty.'
                            );
                        }
                        // Declare dynamically.
                        $this->tableElements = $ruleValue;
                    }
                    // listItems.
                    else {
                        // Allow only specific buckets.
                        if (array_diff(
                            $prop_keys = array_keys(get_object_vars($ruleValue)),
                            static::LIST_ITEMS_ALLOWED_KEYS
                        )) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                                . ' can only contain keys[' . join(', ', static::LIST_ITEMS_ALLOWED_KEYS)
                                . '], saw[' . join(', ', $prop_keys) . '].'
                            );
                        }
                        unset($prop_keys);
                        // minOccur|maxOccur must be non-negative integer.
                        $occur_keys = array('minOccur' , 'maxOccur');
                        foreach ($occur_keys as $occur_key) {
                            if (isset($ruleValue->{$occur_key})) {
                                if (!is_int($ruleValue->{$occur_key}) || $ruleValue->{$occur_key} < 0) {
                                    throw new InvalidRuleException(
                                        'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                                        . ' bucket \'' . $occur_key . '\' type['
                                        . Utils::getType($ruleValue->{$occur_key}) . ']'
                                        . (!is_int($ruleValue->{$occur_key}) ? '' :
                                            ' value[' . $ruleValue->{$occur_key} . ']')
                                        . ' is not non-negative integer.'
                                    );
                                }
                            }
                        }
                        unset($occur_keys, $occur_key);
                        // Positive maxOccur cannot be less than minOccur.
                        // maxOccur:zero means no maximum occurrence.
                        if (
                            isset($ruleValue->maxOccur) && isset($ruleValue->minOccur)
                            && $ruleValue->maxOccur && $ruleValue->maxOccur < $ruleValue->minOccur
                        ) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                                . ' positive maxOccur[' . $ruleValue->maxOccur
                                . '] cannot be less than minOccur[' . $ruleValue->minOccur . '].'
                            );
                        }
                        // itemRules.
                        if (!isset($ruleValue->itemRules)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                                . ' misses ValidationRuleSet|array|object \'itemRules\' bucket.'
                            );
                        }
                        if (!($ruleValue->itemRules instanceof ValidationRuleSet)) {
                            if (is_array($ruleValue->itemRules) || is_object($ruleValue->itemRules)) {
                                // new ValidationRuleSet(.
                                $ruleValue->itemRules = new static(
                                    $ruleValue->itemRules, $provider_info, $depth + 1
                                );
                            } else {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[listItems] at depth[' .  $depth
                                    . '] \'itemRules\' bucket type[' . Utils::getType($ruleValue->itemRules)
                                    . '] is not ValidationRuleSet|array|object.'
                                );
                            }
                        }
                        // Declare dynamically.
                        $this->listItems = $ruleValue;
                    }
                    break;

                default:
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if (isset($rules_found[$rule])) {
                        throw new InvalidRuleException(
                            'Duplicate validation rule[' . $rule . '] at depth[' .  $depth . '].'
                        );
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $provider_info->ruleMethods)) {
                        if (isset(static::RULES_RENAMED[$rule])) {
                            $rule = static::RULES_RENAMED[$rule];
                        }
                        else {
                            throw new InvalidRuleException(
                                'Non-existent validation rule[' . $rule . '] at depth[' . $depth . '].'
                            );
                        }
                    }

                    $rules_found[$rule] = true;

                    switch ($rule) {
                        case 'enum':
                            if (!$args || !is_array($args)) {
                                throw new InvalidRuleException(
                                    'Validation rule[enum] at depth[' .  $depth
                                    . '] value type[' . Utils::getType($args) . '] is not non-empty array.'
                                );
                            }
                            // Allow defining enum as un-nested array, because
                            // counter-intuitive.
                            // enum formally requires to be nested, since
                            // the allowed values array is second argument
                            // to be passed to the enum() method.
                            $allowed_values = reset($args);
                            if (!is_array($allowed_values)) {
                                $allowed_values =& $args;
                            }
                            // Check once and for all that allowed values are scalar|null.
                            $i = -1;
                            foreach ($allowed_values as $allowed) {
                                ++$i;
                                if ($allowed !== null && !is_scalar($allowed)) {
                                    throw new InvalidRuleException(
                                        'Validation rule[enum] at depth[' .  $depth . '] allowed values bucket['
                                        . $i . '] type[' . Utils::getType($allowed) . '] is not scalar or null.'
                                    );
                                }
                            }
                            // Declare dynamically.
                            $this->enum = [
                                $allowed_values
                            ];
                            unset($allowed_values, $allowed);
                            break;
                        default:
                            // Check that rule value accords with whether
                            // the rule method accepts or requires
                            // more argument(s) than subject self.
                            if ($provider_parameter_methods === null) {
                                $provider_parameter_methods = $provider_info->ruleProvider->getParameterMethods();
                            }
                            $rule_takes_arguments = isset($provider_parameter_methods[$rule]);

                            // Rule accept(s) more arguments than subject self.
                            if ($rule_takes_arguments) {
                                // Rule requires argument(s).
                                if ($provider_parameter_methods[$rule]) {
                                    // Rule value must be array.
                                    if (!$args || !is_array($args)) {
                                        throw new InvalidRuleException(
                                            'Validation rule[' . $rule . '] at depth[' .  $depth . '] requires more'
                                            . ' argument(s) than subject self, and rule value type['
                                            . Utils::getType($args) . '] is not non-empty array.'
                                        );
                                    }
                                    // Rule method requires argument(s).
                                    // Declare dynamically.
                                    $this->{$rule} = $args;
                                }
                                // Rule doesn't require argument(s).
                                else {
                                    if ($args === true) {
                                        // Rule method accepts argument(s), but
                                        // none given; true as simple 'on' flag.
                                        // Declare dynamically.
                                        $this->{$rule} = true;
                                    }
                                    elseif (!$args || !is_array($args)) {
                                        // Rule method accepts argument(s);
                                        // if not true it must be non-empty array.
                                        throw new InvalidRuleException(
                                            'Validation rule[' . $rule . '] at depth[' .  $depth . '] accepts more'
                                            . ' argument(s) than subject, but value type[' . Utils::getType($args)
                                            . '] is neither boolean true (simple \'on\' flag), nor non-empty array'
                                            . ' (list of secondary argument(s)).'
                                        );
                                    }
                                    else {
                                        // Declare dynamically.
                                        $this->{$rule} = $args;
                                    }
                                }
                            }
                            // Rule doesn't accept argument(s);
                            // value must be boolean true.
                            elseif ($args === true) {
                                // Declare dynamically.
                                $this->{$rule} = true;
                            }
                            else {
                                // Rule method doesn't accept argument(s);
                                // value should be boolean true.
                                throw new InvalidRuleException(
                                    'Validation rule[' . $rule . '] at depth[' .  $depth . '] doesn\'t accept'
                                    . ' more arguments than subject self, thus value type[' . Utils::getType($args)
                                    . '] makes no sense, value only allowed to be boolean true.'
                                );
                            }
                    }
            }
        }
        // Iteration ref.
        unset($ruleValue);
    }

    /**
     * @var array
     */
    protected static $rulesByProviderClass = [];
}
