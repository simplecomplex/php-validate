<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\OutOfRangeException;

/**
 * Do not use this method directly - use Validate->challenge().
 *
 * @see Validate::challenge()
 *
 * Purpose
 * -------
 * Provides means of:
 * 1) calling more validation methods on a var _by configuration_
 * 2) validating buckets (and sub buckets) of objects and arrays
 *
 *
 * Sequence of secondary rule arg buckets
 * --------------------------------------
 * When a rule takes/requires secondary arguments:
 * The sequence of buckets is essential, keys - whether numeric or associative
 * - are ignored. They will be accessed/used via reset(), next()...
 *
 *
 * Design considerations - proxy class pattern
 * -------------------------------------------
 * The methods and props of this class could in principle be integrated
 * into the Validate class.
 * But it would obscure the primary purpose of the Validate class:
 * to provide simple, directly applicable, validation methods.
 * Having the rule methods in a class separate from this also has the added
 * benefit that it's far simpler to determine which (Validate) methods are
 * rule methods.
 * And this class cannot 'see'/call protected methods of the Validate class.
 *
 *
 * @internal
 *
 * @package SimpleComplex\Validate
 */
class ValidateByRules
{

    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var ValidateByRules
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @see Validate::challenge()
     *
     * @param mixed ...$constructorParams
     *
     * @return ValidateByRules
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * Recursion emergency brake.
     *
     * Ideally the depth of a rule set describing objects/arrays having nested
     * objects/arrays should limit recursion, naturally/orderly.
     * But circular references within the rule set - or a programmatic error
     * in this library - could (without this hardcoded limit) result in
     * perpetual recursion.
     *
     * @var int
     */
    const RECURSION_LIMIT = 10;

    /**
     * Rules that the rules provider doesn't (and shan't) provide.
     *
     * @var array
     */
    const NON_PROVIDER_RULES = [
        'optional',
        'alternativeEnum',
        'tableElements',
        'listItemPrototype',
    ];

    /**
     * The (most of the) methods of the Validate instance will be the rules
     * available.
     *
     * @var RuleProviderInterface
     */
    protected $ruleProvider;

    /**
     * @var array
     */
    protected $ruleMethods = [];

    /**
     * @var bool
     */
    protected $recordFailure = false;

    /**
     * @var array
     */
    protected $record = [];

    /**
     * Use Validate::challenge() instead of this.
     *
     * @see Validate::challenge()
     *
     * @param RuleProviderInterface $ruleProvider
     *      The (most of the) methods of the Validate instance will be
     *      the rules available.
     * @param array $options {
     *      @var bool recordFailure Default: false.
     * }
     */
    public function __construct(
        RuleProviderInterface $ruleProvider,
        array $options = [
            'recordFailure' => false,
        ]
    ) {
        $this->ruleProvider = $ruleProvider;
        $this->recordFailure = !empty($options['recordFailure']);
    }

    /**
     * Use Validate::challenge() instead of this.
     *
     * @code
     * // Validate a value which should be an integer zero thru two.
     * $validate->ruleSet($some_input, [
     *   'integer',
     *   'range' => [
     *     0,
     *     2
     *   ]
     * ]);
     * @endcode
     *
     * @uses get_class_methods()
     * @uses Validate::getNonRuleMethods()
     *
     * @param mixed $var
     * @param array|object $rules
     *      A list of rules; either 'rule':[specs] or N:'rule'.
     *      [
     *          'integer'
     *          'range': [ 0, 2 ]
     *      ]
     *
     * @return bool
     *
     * @throws \TypeError
     *      Arg rules not array|object.
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($var, $rules)
    {
        if (!is_array($rules) && !is_object($rules)) {
            throw new \TypeError(
                'Arg rules type[' . (!is_object($rules) ? gettype($rules) : get_class($rules))
                . '] is not array|object.'
            );
        }
        // Init, really.
        // List rule methods made available by the rule provider.
        if (!$this->ruleMethods) {
            $this->ruleMethods = array_diff(
                get_class_methods(get_class($this->ruleProvider)),
                $this->ruleProvider->getNonRuleMethods()
            );
        }

        return $this->internalChallenge(0, '', $var, $rules);
    }

    /**
     * Internal method to accommodate an inaccessible depth argument,
     * to control/limit recursion.
     *
     * @recursive
     *
     * @param int $depth
     * @param string $keyPath
     * @param mixed $var
     * @param array|object $rules
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     */
    protected function internalChallenge($depth, $keyPath, $var, $rules)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $rules_found = $alternative_enum = $table_elements = $list_item_prototype = [];
        foreach ($rules as $k => $v) {
            if (ctype_digit('' . $k)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $v;
                $args = true;
            } else {
                // Bucket key is name of the rule,
                // value is arguments for the rule method.
                $rule = $k;
                $args = $v;
            }
            switch ($rule) {
                case 'optional':
                    // Do nothing, ignore here.
                    // Only used when working on tableElements|listItemPrototype.
                    break;
                case 'alternativeEnum':
                case 'tableElements':
                case 'listItemPrototype':
                    $arg_type = $this->ruleProvider->container($args);
                    if (!$arg_type) {
                        throw new InvalidArgumentException(
                            'Args for validation rule[' . $rule
                            . '] type[' . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not a container.'
                        );
                    }
                    if ($arg_type == 'array') {
                        $args_array =& $args;
                    } else {
                        $args_array = (array) $args;
                    }
                    if (!$args_array) {
                        if (!$arg_type) {
                            throw new InvalidArgumentException(
                                'Args for validation rule[' . $rule
                                . '] type[' . (!is_object($args) ? gettype($args) : get_class($args))
                                . '] is empty.'
                            );
                        }
                    }
                    switch ($rule) {
                        case 'alternativeEnum':
                            $alternative_enum = $args_array;
                            break;
                        case 'tableElements':
                            $table_elements = $args_array;
                            break;
                        case 'listItemPrototype':
                            $list_item_prototype = $args_array;
                            break;
                    }
                    break;
                default:
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if ($rules_found && isset($rules_found[$rule])) {
                        throw new InvalidArgumentException('Duplicate validation rule[' . $rule . '].');
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $this->ruleMethods)) {
                        throw new InvalidArgumentException('Non-existent validation rule[' . $rule . '].');
                    }

                    $rules_found[$rule] = $args;
            }
        }

        // Roll it.
        $failed = false;
        $record = [];
        foreach ($rules_found as $rule => $args) {
            // We expect more boolean trues than arrays;
            // few Validate methods take secondary args.
            if (!$args || $args === true || !is_array($args)) {
                if (!$this->ruleProvider->{$rule}($var)) {
                    $failed = true;
                    if ($this->recordFailure) {
                        $record[] = $rule;
                    }
                    break;
                }
            } elseif (!$this->ruleProvider->{$rule}($var, ...$args)) {
                $failed = true;
                if ($this->recordFailure) {
                    $record[] = $rule;
                }
                break;
            }
        }

        if ($failed) {
            // Matches one of a list of alternative (scalar|null) values?
            if ($alternative_enum) {
                if ($this->ruleProvider->enum($var, $alternative_enum)) {
                    return true;
                }
                if ($this->recordFailure) {
                    $this->record[] = $keyPath . ': ' . join(', ', $record) . ', alternativeEnum';
                }
                return false;
            }
            if ($this->recordFailure) {
                $this->record[] = $keyPath . ': ' . join(', ', $record);
            }
            return false;
        }

        // Didn't fail.
        if (!$table_elements && !$list_item_prototype) {
            return true;
        }

        // Do 'tableElements'.
        // Check that input var is an object or array, and get which type.
        $container_type = $this->ruleProvider->container($var);
        if (!$container_type) {
            // A-OK: one should - for convenience - be allowed to use
            // the 'tableElements' rule, without explicitly declaring/using
            // a container type checker.
            if ($this->recordFailure) {
                $this->record[] = $keyPath . ': tableElements - ' . gettype($var) . ' is not a container';
            }
            return false;
        }

        // tableElements + listItemPrototype is allowed.
        // Relevant for a container derived from XML, which allows hash table
        // elements and list items within the same container (XML sucks ;-).
        // To prevent collision (repeated validation of elements) we filter
        // declared tableElements out of list validation.
        $element_list_skip_keys = [];
        if ($table_elements) {
            // Iterate array|object separately, don't want to clone object to array.
            switch ($container_type) {
                case 'array':
                case 'arrayAccess':
                    $is_array = $container_type == 'array';
                    foreach ($table_elements as $key => $sub_rules) {
                        if ($is_array ? !array_key_exists($key, $var) : !$var->offsetExists($key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($sub_rules['optional']) && !in_array('optional', $sub_rules)) {
                                if ($this->recordFailure) {
                                    // We don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $element_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge(
                                $depth + 1, $keyPath . '[' . $key . ']', $var[$key], $sub_rules)
                            ) {
                                if ($this->recordFailure) {
                                    // We don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        }
                    }
                    break;
                default:
                    // Object.
                    foreach ($table_elements as $key => $sub_rules) {
                        if (!property_exists($var, $key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($sub_rules['optional']) && !in_array('optional', $sub_rules)) {
                                if ($this->recordFailure) {
                                    // We don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $element_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge($depth + 1, $keyPath . '->' . $key, $var->{$key}, $sub_rules)) {
                                if ($this->recordFailure) {
                                    // We don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        }
                    }
            }
        }

        if ($list_item_prototype) {
            switch ($container_type) {
                case 'array':
                case 'arrayAccess':
                    $prefix = '[';
                    $suffix = ']';
                    break;
                default:
                    $prefix = '->';
                    $suffix = '';
            }
            foreach ($var as $index => $item) {
                if (!$element_list_skip_keys || !in_array($index, $element_list_skip_keys, true)) {
                    // Recursion.
                    if (!$this->internalChallenge(
                        $depth + 1, $keyPath . $prefix . $index . $suffix, $item, $list_item_prototype)
                    ) {
                        if ($this->recordFailure) {
                            // We don't stop on failure when recording.
                            continue;
                        }
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getRecord() {
        return $this->record;
    }
}
