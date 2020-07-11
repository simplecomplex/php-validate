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
     * @var RuleProviderInfo
     */
    protected $ruleProviderInfo;

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

        if (!is_array($rules)) {
            if (is_object($rules)) {
                if ($rules instanceof \ArrayAccess) {
                    throw new \InvalidArgumentException(
                        'Arg rules at depth[' . $depth . '] type[' . static::getType($rules)
                        . '] \ArrayAccess is not supported.'
                    );
                }
            }
            else {
                throw new \InvalidArgumentException(
                    'Arg rules at depth[' . $depth . '] type[' . static::getType($rules) . '] is not array|object.'
                );
            }
        }

        if (!$ruleProvider) {
            // Go for default; presumably set in dependency injection container.
            $this->ruleProviderInfo = new RuleProviderInfo();
        }
        elseif ($ruleProvider instanceof RuleProviderInfo) {
            $this->ruleProviderInfo = $ruleProvider;
        }
        elseif ($ruleProvider instanceof RuleProviderInterface) {
            $this->ruleProviderInfo = new RuleProviderInfo($ruleProvider);
        }
        else {
            throw new \InvalidArgumentException(
                'Arg ruleProvider at depth[' . $depth . '] type[' . static::getType($ruleProvider)
                . '] is not RuleProviderInterface|RuleProviderInfo|null.'
            );
        }

        $type_rules_supported = $this->ruleProviderInfo->typeMethods;

        // Ensure that there's a type checking method,
        // and that it goes at the top; before rules that don't type check.
        // Set all type rules at top, and remove the unecessary later.
        foreach ($type_rules_supported as $method) {
            // The 'class' method cannot be true; fix later.
            $this->{$method} = true;
        }

        $type_rules_found = [];
        // List of provider rules taking arguments.
        $provider_parameter_methods = null;

        foreach ($rules as $method => $argument) {
            if (ctype_digit('' . $method)) {
                throw new InvalidRuleException(
                    'Validation rule name by value instead of key is no longer supported'
                    . ', saw numeric index[' . $method . ']'
                    . (!is_string($argument) ? '' : (' value[' . $argument . ']'))
                    . ', at depth[' .  $depth . '].'
                );
            }
            if (in_array($method, $type_rules_supported)) {
                $type_rules_found[] = $method;
                if ($method == 'class') {
                    $this->class = $argument;
                }
                // Otherwise ignore; already set (true).
            }
            else {
                // Copy because may alter.
                $arg = $argument;

                switch ($method) {
                    case 'optional':
                    case 'allowNull':
                        if ($arg) {
                            // Declare dynamically.
                            $this->{$method} = true;
                        }
                        break;

                    case 'alternativeEnum':
                        if (!$arg || !is_array($arg)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[alternativeEnum] at depth[' .  $depth
                                . '] type[' . static::getType($arg) . '] is not non-empty array.'
                            );
                        }
                        // Allow defining alternativeEnum as nested array, because
                        // easy to confuse with the format for enum.
                        // enum formally requires to be nested, since
                        // the allowed values array is second argument
                        // to be passed to the enum() method.
                        if (is_array(reset($arg))) {
                            $arg = current($arg);
                        }

                        // Check once and for all that allowed values are scalar|null.
                        $i = -1;
                        foreach ($arg as $value) {
                            ++$i;
                            if ($value !== null && !is_scalar($value)) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[alternativeEnum] at depth[' .  $depth
                                    . '] allowed values bucket[' . $i . '] type[' . static::getType($value)
                                    . '] is not scalar or null.'
                                );
                            }
                        }
                        // Declare dynamically.
                        $this->alternativeEnum = $arg;
                        break;

                    case 'alternativeRuleSet':
                        if ($arg instanceof ValidationRuleSet) {
                            // Do not allow alternativeRuleSet to have alternativeRuleSet.
                            if (!empty($arg->alternativeRuleSet)) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[alternativeRuleSet] at depth[' .  $depth
                                    . '] is not allowed to have alternativeRuleSet by itself.'
                                );
                            }
                            $this->alternativeRuleSet = $arg;
                        }
                        else {
                            // new ValidationRuleSet(.
                            $this->alternativeRuleSet = new static($arg, $this->ruleProviderInfo, $depth + 1);
                        }
                        break;

                    case 'tableElements':
                    case 'listItems':
                        if (is_array($arg)) {
                            $arg = (object) $arg;
                        }
                        elseif (!is_object($arg)) {
                            $msg = 'Non-provider validation rule[' . $method . '] at depth[' .  $depth
                                . '] type[' . static::getType($arg) . '] is not a array|object.';
//                            $container = Dependency::container();
//                            if ($container->has('logger')) {
//                                if ($container->has('inspect')) {
//                                    $inspection = $container->get('inspect')->variable($rules);
//                                } else {
//                                    $inspection = 'Keys['
//                                        . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
//                                }
//                                $container->get('logger')->warning($msg . "\n" . $inspection);
//                            }
                            throw new InvalidRuleException($msg);
                        }

                        if ($method == 'tableElements') {
                            $this->tableElements($arg, $depth);
                            // Declare dynamically.
                            $this->tableElements = $arg;
                        }
                        // listItems.
                        else {
                            $this->listItems($arg, $depth);
                            // Declare dynamically.
                            $this->listItems = $arg;
                        }
                        break;

                    default:
                        // Check rule method existance.
                        if (!in_array($method, $this->ruleProviderInfo->ruleMethods)) {
                            if (isset(static::RULES_RENAMED[$method])) {
                                $method = static::RULES_RENAMED[$method];
                            }
                            else {
                                throw new InvalidRuleException(
                                    'Non-existent validation rule[' . $method . '] at depth[' . $depth . '].'
                                );
                            }
                        }

                        switch ($method) {
                            case 'enum':
                                if (!$arg || !is_array($arg)) {
                                    throw new InvalidRuleException(
                                        'Validation rule[enum] at depth[' .  $depth
                                        . '] value type[' . static::getType($arg) . '] is not non-empty array.'
                                    );
                                }
                                // Allow defining enum as un-nested array, because
                                // counter-intuitive.
                                // enum formally requires to be nested, since
                                // the allowed values array is second argument
                                // to be passed to the enum() method.
                                $allowed_values = reset($arg);
                                if (!is_array($allowed_values)) {
                                    $allowed_values = $arg;
                                }
                                // Check once and for all that allowed values are scalar|null.
                                $i = -1;
                                foreach ($allowed_values as $value) {
                                    ++$i;
                                    if ($value !== null && !is_scalar($value)) {
                                        throw new InvalidRuleException(
                                            'Validation rule[enum] at depth[' .  $depth . '] allowed values bucket['
                                            . $i . '] type[' . static::getType($value) . '] is not scalar or null.'
                                        );
                                    }
                                }
                                // Declare dynamically.
                                $this->enum = [
                                    $allowed_values
                                ];
                                break;
                            default:
                                // Check that rule value accords with whether
                                // the rule method accepts or requires
                                // more argument(s) than subject self.
                                if ($provider_parameter_methods === null) {
                                    $provider_parameter_methods =
                                        $this->ruleProviderInfo->ruleProvider->getParameterMethods();
                                }
                                $rule_takes_arguments = isset($provider_parameter_methods[$method]);

                                // Rule accept(s) more arguments than subject self.
                                if ($rule_takes_arguments) {
                                    // Rule requires argument(s).
                                    if ($provider_parameter_methods[$method]) {
                                        // Rule value must be array.
                                        if (!$arg || !is_array($arg)) {
                                            throw new InvalidRuleException(
                                                'Validation rule[' . $method . '] at depth[' .  $depth . '] requires more'
                                                . ' argument(s) than subject self, and rule value type['
                                                . static::getType($arg) . '] is not non-empty array.'
                                            );
                                        }
                                        // Rule method requires argument(s).
                                        // Declare dynamically.
                                        $this->{$method} = $arg;
                                    }
                                    // Rule doesn't require argument(s).
                                    else {
                                        if ($arg === true) {
                                            // Rule method accepts argument(s), but
                                            // none given; true as simple 'on' flag.
                                            // Declare dynamically.
                                            $this->{$method} = true;
                                        }
                                        elseif (!$arg || !is_array($arg)) {
                                            // Rule method accepts argument(s);
                                            // if not true it must be non-empty array.
                                            throw new InvalidRuleException(
                                                'Validation rule[' . $method . '] at depth[' .  $depth . '] accepts more'
                                                . ' argument(s) than subject, but value type[' . static::getType($arg)
                                                . '] is neither boolean true (simple \'on\' flag), nor non-empty array'
                                                . ' (list of secondary argument(s)).'
                                            );
                                        }
                                        else {
                                            // Declare dynamically.
                                            $this->{$method} = $arg;
                                        }
                                    }
                                }
                                // Rule doesn't accept argument(s);
                                // value must be boolean true.
                                elseif ($arg === true) {
                                    // Declare dynamically.
                                    $this->{$method} = true;
                                }
                                else {
                                    // Rule method doesn't accept argument(s);
                                    // value should be boolean true.
                                    throw new InvalidRuleException(
                                        'Validation rule[' . $method . '] at depth[' .  $depth . '] doesn\'t accept'
                                        . ' more arguments than subject self, thus value type[' . static::getType($arg)
                                        . '] makes no sense, value only allowed to be boolean true.'
                                    );
                                }
                        }
                }
            }
        }

        // Remove all the method properties set initially, except those actually
        // to be used.
        if (!$type_rules_found) {
            // Default to string unless object|array; then container.
            if (isset($this->tableElements) || isset($this->listItems)) {
                $type_rules_found[] = 'container';
            }
            else {
                $type_rules_found[] = 'string';
            }
        }
        $type_rules_remove = array_diff($type_rules_supported, $type_rules_found);
        foreach ($type_rules_remove as $method) {
            unset($this->{$method});
        }
    }

    protected function tableElements(object $arg, int $depth)
    {
        // Fix case spelling errors.
        if (isset($arg->whiteList)) {
            $arg->whitelist = $arg->whiteList;
            unset($arg->whiteList);
        }
        if (isset($arg->blackList)) {
            $arg->blacklist = $arg->blackList;
            unset($arg->blackList);
        }
        // Allow only specific buckets.
        if (array_diff(
            $prop_keys = array_keys(get_object_vars($arg)),
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
        if (isset($arg->exclusive)) {
            if (!is_bool($arg->exclusive)) {
                throw new InvalidRuleException(
                    'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                    . ' bucket \'exclusive\' type[' . static::getType($arg->exclusive)
                    . '] is not boolean.'
                );
            }
            elseif ($arg->exclusive) {
                $has_lists[] = 'exclusive';
            }
        }
        // whitelist|blacklist must be array, but allowed empty.
        $list_keys = array('whitelist' , 'blacklist');
        foreach ($list_keys as $list_key) {
            if (isset($arg->{$list_key})) {
                if (!is_array($arg->{$list_key})) {
                    throw new InvalidRuleException(
                        'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                        . ' bucket \'' . $list_key . '\' type['
                        . static::getType($arg->{$list_key}) . '] is not array.'
                    );
                }
                elseif ($arg->{$list_key}) {
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
        if (!isset($arg->rulesByElements)) {
            throw new InvalidRuleException(
                'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                . ' misses array|object \'rulesByElements\' bucket.'
            );
        }
        if (is_array($arg->rulesByElements)) {
            $arg->rulesByElements = (object) $arg->rulesByElements;
        }
        elseif (!is_object($arg->rulesByElements)) {
            $msg = 'Non-provider validation rule[tableElements] at depth[' .  $depth . ']'
                . ' bucket \'rulesByElements\' type[' . static::getType($arg->rulesByElements)
                . '] is not a array|object.';
//            $container = Dependency::container();
//            if ($container->has('logger')) {
//                if ($container->has('inspect')) {
//                    $inspection = $container->get('inspect')->variable($rules);
//                } else {
//                    $inspection = 'Keys['
//                        . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
//                }
//                $container->get('logger')->warning($msg . "\n" . $inspection);
//            }
            throw new InvalidRuleException($msg);
        }
        try {
            $index = -1;
            foreach ($arg->rulesByElements as $elementName => &$subRuleSet) {
                $name = $elementName;
                ++$index;
                if (!($subRuleSet instanceof ValidationRuleSet)) {
                    if (is_array($subRuleSet) || is_object($subRuleSet)) {
                        // new ValidationRuleSet(.
                        $subRuleSet = new static(
                            $subRuleSet, $this->ruleProviderInfo, $depth + 1
                        );
                    } else {
                        throw new InvalidRuleException(
                            'Element rule set type[' . static::getType($subRuleSet)
                            . '] is not ValidationRuleSet|array|object.'
                        );
                    }
                }
            }
            // Iteration ref.
            unset($subRuleSet);
        }
        catch (\Throwable $xc) {
            $msg = 'Non-provider validation rule[tableElements] at depth[' .  $depth
                . '] element index[' . $index . '] name[' . $name
                . '] is not a valid rule set.';
//            $container = Dependency::container();
//            if ($container->has('logger')) {
//                if ($container->has('inspect')) {
//                    $inspection = 'Current rule set:'
//                        . "\n" . $container->get('inspect')->variable($rules)->toString(false);
//                } else {
//                    $inspection = 'Current rule set keys['
//                        . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
//                }
//                $container->get('logger')->warning(
//                    $msg . "\n" . $xc->getMessage() . "\n" . $inspection
//                );
//            }
            throw new InvalidRuleException($msg . ' ' . $xc->getMessage());
        }
        if ($index == -1) {
            throw new InvalidRuleException(
                'Non-provider validation rule[tableElements] at depth[' .  $depth . '] is empty.'
            );
        }
    }


    protected function listItems(object $arg, int $depth)
    {
        // Allow only specific buckets.
        if (array_diff(
            $prop_keys = array_keys(get_object_vars($arg)),
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
            if (isset($arg->{$occur_key})) {
                if (!is_int($arg->{$occur_key}) || $arg->{$occur_key} < 0) {
                    throw new InvalidRuleException(
                        'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                        . ' bucket \'' . $occur_key . '\' type['
                        . static::getType($arg->{$occur_key}) . ']'
                        . (!is_int($arg->{$occur_key}) ? '' :
                            ' value[' . $arg->{$occur_key} . ']')
                        . ' is not non-negative integer.'
                    );
                }
            }
        }
        unset($occur_keys, $occur_key);
        // Positive maxOccur cannot be less than minOccur.
        // maxOccur:zero means no maximum occurrence.
        if (
            isset($arg->maxOccur) && isset($arg->minOccur)
            && $arg->maxOccur && $arg->maxOccur < $arg->minOccur
        ) {
            throw new InvalidRuleException(
                'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                . ' positive maxOccur[' . $arg->maxOccur
                . '] cannot be less than minOccur[' . $arg->minOccur . '].'
            );
        }
        // itemRules.
        if (!isset($arg->itemRules)) {
            throw new InvalidRuleException(
                'Non-provider validation rule[listItems] at depth[' .  $depth . ']'
                . ' misses ValidationRuleSet|array|object \'itemRules\' bucket.'
            );
        }
        if (!($arg->itemRules instanceof ValidationRuleSet)) {
            if (is_array($arg->itemRules) || is_object($arg->itemRules)) {
                // new ValidationRuleSet(.
                $arg->itemRules = new static(
                    $arg->itemRules, $this->ruleProviderInfo, $depth + 1
                );
            } else {
                throw new InvalidRuleException(
                    'Non-provider validation rule[listItems] at depth[' .  $depth
                    . '] \'itemRules\' bucket type[' . static::getType($arg->itemRules)
                    . '] is not ValidationRuleSet|array|object.'
                );
            }
        }
    }

    /**
     * Get subject class name or (non-object) type.
     *
     * Counter to native gettype() this method returns:
     * - class name instead of 'object'
     * - 'float' instead of 'double'
     * - 'null' instead of 'NULL'
     *
     * Like native gettype() this method returns:
     * - 'boolean' not 'bool'
     * - 'integer' not 'int'
     * - 'unknown type' for unknown type
     *
     * @param mixed $subject
     *
     * @return string
     */
    protected static function getType($subject)
    {
        if (!is_object($subject)) {
            $type = gettype($subject);
            switch ($type) {
                case 'double':
                    return 'float';
                case 'NULL':
                    return 'null';
                default:
                    return $type;
            }
        }
        return get_class($subject);
    }
}
