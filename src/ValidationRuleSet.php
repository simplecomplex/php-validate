<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Validate\Exception\InvalidRuleException;

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
 * @property array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *
 *      Bucket values must be scalar|null.
 *
 *      Only declared if relevant, otherwise undefined.
 *
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
     * @see ValidationRuleSet::NON_PROVIDER_RULES
     */

    /**
     * Validation rule set.
     *
     * @see ValidationRuleSet::ruleMethodsAvailable()
     *
     * @param array|object $rules
     * @param array $ruleMethodsAvailable
     * @param int $depth
     *
     * @throws InvalidRuleException
     */
    public function __construct($rules, array $ruleMethodsAvailable = [], $depth = 0)
    {
        $rule_methods = $ruleMethodsAvailable ? $ruleMethodsAvailable : static::ruleMethodsAvailable();

        $rules_found = [];

        foreach ($rules as $ruleKey => &$ruleValue) {
            unset($args);
            if (ctype_digit('' . $ruleKey)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $ruleValue;
                $args = true;
                // The only non-provider rules supported is 'optional';
                // the others cannot be boolean.
                // And only provider rules having no parameters are legal here.
                switch ($rule) {
                    case 'optional':
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
                        if (!in_array($rule, $rule_methods)) {
                            throw new InvalidRuleException(
                                'Non-existent validation rule[' . $rule . '] at depth[' .  $depth . '],'
                                . ' was attempted to be defined via numeric key and bucket value.'
                            );
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

            switch ($rule) {
                case 'optional':
                    if ($depth && $args) {
                        $this->optional = true;
                    }
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
                    $this->alternativeEnum =& $allowed_values;
                    unset($allowed_values, $allowed);
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
                                $inspection = $container->get('inspect')->variable();
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
                                    $inspection = $container->get('inspect')->variable();
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
                                        $subRuleSet = new static(
                                            $subRuleSet, $rule_methods, $depth + 1
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
                                . '] element index[' . $index . '] name[' . $name . ']'
                                . '] is not a valid rule set.';
                            $container = Dependency::container();
                            if ($container->has('logger')) {
                                if ($container->has('inspect')) {
                                    $inspection = $container->get('inspect')->variable();
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
                                . '] cannot be less that minOccur[' . $ruleValue->minOccur . '].'
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
                                $ruleValue->itemRules = new static(
                                    $ruleValue->itemRules, $rule_methods, $depth + 1
                                );
                            } else {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[listItems] at depth[' .  $depth
                                    . '] \'itemRules\' bucket type[' . Utils::getType($ruleValue->itemRules)
                                    . '] is not ValidationRuleSet|array|object.'
                                );
                            }
                        }
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
                    if (!in_array($rule, $rule_methods)) {
                        throw new InvalidRuleException(
                            'Non-existent validation rule[' . $rule . '] at depth[' .  $depth . '].'
                        );
                    }

                    if (!is_bool($args) && !is_array($args)) {
                        throw new InvalidRuleException(
                            'Validation rule[' . $rule . '] at depth[' .  $depth . '] type[' . Utils::getType($args)
                            . '] is not boolean or array.'
                        );
                    }

                    $rules_found[$rule] = true;

                    switch ($rule) {
                        case 'enum':
                            if (!$args || !is_array($args)) {
                                throw new InvalidRuleException(
                                    'Validation rule[enum] at depth[' .  $depth
                                    . '] type[' . Utils::getType($args) . '] is not non-empty array.'
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
                            $this->enum = [
                                $allowed_values
                            ];
                            unset($allowed_values, $allowed);
                            break;
                        default:
                            $this->{$rule} = $args;
                    }
            }
        }
        // Iteration ref.
        unset($ruleValue);
    }

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
     * List rule methods made available by a rule provider.
     *
     * Helper method. Has to be set on other class than Validate
     * (because Validate can 'see' it's own protected methods)
     * and ValidateAgainstRuleSet (because that's marked as internal).
     *
     * @see Validate
     *
     * @uses get_class_methods()
     * @uses Validate::getNonRuleMethods()
     *
     * @param RuleProviderInterface|null $ruleProvider
     *      Default: dependency container ID 'validator' or Validate::getInstance().
     *
     * @return array
     */
    public static function ruleMethodsAvailable(/*?RuleProviderInterface*/ $ruleProvider = null)
    {
        if (!$ruleProvider) {
            $container = Dependency::container();
            $provider = $container->has('validator') ? $container->get('validator') : Validate::getInstance();
        } else {
            $provider = $ruleProvider;
        }
        return array_diff(
            get_class_methods(get_class($provider)),
            $provider->getNonRuleMethods()
        );
    }
}
