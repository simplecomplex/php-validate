<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Exception\OutOfRangeException;

/**
 * Do not use this method directly - use Validate->challenge().
 *
 * @see Validate::challenge()
 *
 * Purpose
 * -------
 * Provides means of:
 * 1) calling more validation methods on a subject _by configuration_
 * 2) validating buckets (and sub buckets) of objects and arrays
 *
 * Design considerations - proxy class pattern
 * -------------------------------------------
 * The methods and props of this class could in principle be integrated
 * into the Validate class.
 * But it would obscure the primary purpose of the Validate class:
 * to provide simple and directly applicable validation methods.
 * Having the rule methods in a class separate from this also has the added
 * benefit that it's far simpler to determine which (Validate) methods are
 * rule methods.
 *
 * @see simple_complex_validate_test_cli()
 *      For example of use.
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
     * @uses ValidationRuleSet::ruleMethodsAvailable()
     *
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     *      A list of rules; either N:'rule' or 'rule':true or 'rule':[specs].
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
    public function challenge($subject, $ruleSet)
    {
        if (!is_array($ruleSet) && !is_object($ruleSet)) {
            throw new \TypeError(
                'Arg rules type[' . (!is_object($ruleSet) ? gettype($ruleSet) : get_class($ruleSet))
                . '] is not array|object.'
            );
        }
        // Init, really.
        // List rule methods made available by the rule provider.
        if (!$this->ruleMethods) {
            $this->ruleMethods = ValidationRuleSet::ruleMethodsAvailable($this->ruleProvider);
        }

        return $this->internalChallenge(0, '', $subject, $ruleSet);
    }

    /**
     * Internal method to accommodate an inaccessible depth argument,
     * to control/limit recursion.
     *
     * @recursive
     *
     * @param int $depth
     * @param string $keyPath
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return bool
     *
     * @throws InvalidRuleException
     * @throws OutOfRangeException
     */
    protected function internalChallenge($depth, $keyPath, $subject, $ruleSet)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $rules_found = $alternative_enum = $table_elements = $list_item_prototype = [];
        foreach ($ruleSet as $ruleKey => $ruleValue) {
            if (ctype_digit('' . $ruleKey)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $ruleValue;
                $args = true;
            } else {
                // Bucket key is name of the rule,
                // value is arguments for the rule method.
                $rule = $ruleKey;
                $args = $ruleValue;
            }
            switch ($rule) {
                case 'optional':
                    // Do nothing, ignore here.
                    // Only used when working on tableElements|listItemPrototype.
                    break;
                case 'alternativeEnum':
                    // @todo: remove falsy and is_array() checks; rely on checks performed by ValidationRuleSet constructor.
                    if (!$args || !is_array($args)) {
                        throw new InvalidRuleException(
                            'Non-provider validation rule[alternativeEnum] type['
                            . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not non-empty array.'
                        );
                    }
                    $alternative_enum = $args;
                    break;
                case 'tableElements':
                case 'listItemPrototype':
                    // @todo: remove type checks; rely on checks performed by ValidationRuleSet constructor.
                    // @todo: use simple is_array()|is_object, like ValidationRuleSet does.
                    $arg_type = $this->ruleProvider->container($args);
                    if (!$arg_type) {
                        throw new InvalidRuleException(
                            'Non-provider validation rule[' . $rule
                            . '] type[' . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not a array|object.'
                        );
                    }
                    if ($arg_type == 'array') {
                        $args_array = $args;
                    } else {
                        $args_array = (array) $args;
                    }
                    if (!$args_array) {
                        throw new InvalidRuleException(
                            'Non-provider validation rule[' . $rule
                            . '] type[' . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is empty.'
                        );
                    }
                    switch ($rule) {
                        case 'tableElements':
                            $table_elements = $args_array;
                            break;
                        case 'listItemPrototype':
                            $list_item_prototype = $args_array;
                            break;
                    }
                    break;
                default:
                    // @todo: remove dupe check; rely on checks performed by ValidationRuleSet constructor.
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if ($rules_found && isset($rules_found[$rule])) {
                        throw new InvalidRuleException('Duplicate validation rule[' . $rule . '].');
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $this->ruleMethods)) {
                        throw new InvalidRuleException('Non-existent validation rule[' . $rule . '].');
                    }

                    $rules_found[$rule] = $args;
            }
        }

        // Roll it.
        $failed = false;
        $record = [];
        foreach ($rules_found as $rule => $args) {

            // @todo: use

            // We expect more boolean trues than arrays;
            // few Validate methods take secondary args.
            // @todo: remove falsy and is_array() checks; rely on checks performed by ValidationRuleSet constructor.
            if (!$args || $args === true || !is_array($args)) {
                if (!$this->ruleProvider->{$rule}($subject)) {
                    $failed = true;
                    if ($this->recordFailure) {
                        $record[] = $rule;
                    }
                    break;
                }
            } elseif (!$this->ruleProvider->{$rule}($subject, ...$args)) {
                // @todo: declare and use own (fast) enum() method; rely on allowed values check performed by ValidationRuleSet constructor.
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
                // @todo: declare and use own (fast) enum() method; rely on allowed values check performed by ValidationRuleSet constructor.
                if ($this->ruleProvider->enum($subject, $alternative_enum)) {
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
        // Check that subject is an object or array, and get which type.
        $container_type = $this->ruleProvider->container($subject);
        if (!$container_type) {
            // A-OK: one should - for convenience - be allowed to use
            // the 'tableElements' and/or 'list_item_prototype' rule, without
            // explicitly defining/using a container type checker.
            if ($this->recordFailure) {
                $this->record[] = $keyPath . ': tableElements - ' . gettype($subject) . ' is not a container';
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
                        if ($is_array ? !array_key_exists($key, $subject) : !$subject->offsetExists($key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (is_array($sub_rules)) {
                                if (empty($sub_rules['optional']) && !in_array('optional', $sub_rules, true)) {
                                    if ($this->recordFailure) {
                                        // We don't stop on failure when recording.
                                        continue;
                                    }
                                    return false;
                                }
                            } elseif (empty($sub_rules->optional)) {
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
                                $depth + 1, $keyPath . '[' . $key . ']', $subject[$key], $sub_rules)
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
                        if (!property_exists($subject, $key)) {
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
                            if (!$this->internalChallenge($depth + 1, $keyPath . '->' . $key, $subject->{$key}, $sub_rules)) {
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
            foreach ($subject as $index => $item) {
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
