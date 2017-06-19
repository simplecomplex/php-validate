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
 * Example
 * -------
 * @see Validate::challengeRules()
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
     * @param mixed ...$constructorParams
     *
     * @return ValidateByRules
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'validate';

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
        '_elements_',
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
     * Do always throw Exception on logical/runtime error, even when logger
     * available (default not).
     * Ignored for recursion limit excess.
     *
     * @var bool
     */
    protected $errUnconditionally = false;

    /**
     * @var bool
     */
    protected $recordFailure = false;

    /**
     * @var array
     */
    protected $record = [];

    /**
     * Use Validate::challengeRules() instead of this.
     *
     * Uses the logger (if any) of the rule provider.
     * But only on demand; doesn't refer it.
     *
     * @code
     * $logger = new JsonLog();
     * $validate = Validate::getInstance($logger);
     * $validateByRules = ValidateByRules::getInstance($validate);
     * @endcode
     *
     * @see Validate::challengeRules()
     *
     * @param RuleProviderInterface $ruleProvider
     *      The (most of the) methods of the Validate instance will be
     *      the rules available.
     * @param array $options {
     *      @var bool errUnconditionally Default: false.
     *      @var bool recordFailure Default: false.
     * }
     */
    public function __construct(
        RuleProviderInterface $ruleProvider,
        array $options = array(
            'errUnconditionally' => false,
            'recordFailure' => false,
        )
    ) {
        $this->ruleProvider = $ruleProvider;

        $this->errUnconditionally = !empty($options['errUnconditionally']);
        $this->recordFailure = !empty($options['recordFailure']);
    }

    /**
     * Use Validate::challengeRules() instead of this.
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
     * @param array $rules
     *      A list of rules; either 'rule':[specs] or N:'rule'.
     *      [
     *          'integer'
     *          'range': [ 0, 2 ]
     *      ]
     *
     * @return bool
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($var, array $rules)
    {
        // Init, really.
        // List rule methods made available by the rule provider.
        if (!$this->ruleMethods) {
            $this->ruleMethods = array_diff(
                get_class_methods(get_class($this->ruleProvider)),
                $this->ruleProvider->getNonRuleMethods()
            );
        }

        try {
            return $this->internalChallenge(0, '', $var, $rules);
        }
        catch (\Throwable $xc) {
            // Out-library exception: log before propagating.
            $cls = get_class($xc);
            if (
                strpos($cls, __NAMESPACE__ . '\\Exception') !== 0
                // Utils also logs it's own exceptions.
                && strpos($cls, '\\SimpleComplex\\Utils\\Exception') !== 0
            ) {
                $logger = $this->ruleProvider->getLogger();
                if ($logger) {
                    $logger->warning('Validation by rule list failed due to an external error.', [
                        'type' => static::LOG_TYPE,
                        'exception' => $xc,
                    ]);
                }
            }
            throw $xc;
        }
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
     * @param array $ruleSet
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     */
    protected function internalChallenge($depth, $keyPath, $var, $ruleSet)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            $logger = $this->ruleProvider->getLogger();
            if ($logger) {
                $logger->warning(
                    'Stopped recursive validation by rule-set at limit {recursion_limit}, at key path[{key_path}].',
                    [
                        'type' => static::LOG_TYPE,
                        'recursion_limit' => static::RECURSION_LIMIT,
                        'key_path' => $keyPath,
                    ]
                );
                // No 'errUnconditionally' here.
                // Recursion can be dangerous, and exceeding the limit may only
                // happen with an erratical rule list;
                // too deep, or circular reference.
            }
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $rules_found = $alternative_enum = $elements = [];
        foreach ($ruleSet as $k => $v) {
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
                    // Only used when working on '_elements_'.
                    break;
                case 'alternativeEnum':
                case '_elements_':
                    // We know that these rules require non-empty array args.
                    if (!$args || !is_array($args)) {
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {
                            $logger->warning(
                                'Args for validation rule \'{rule_method}\' must be non-empty array, saw[{args_type}]'
                                . ', at key path[{key_path}].',
                                [
                                    'type' => static::LOG_TYPE,
                                    'rule_method' => $rule,
                                    'args_type' => gettype($args),
                                    'key_path' => $keyPath,
                                ]
                            );
                            if (!$this->errUnconditionally) {
                                if ($this->recordFailure) {
                                    $this->record[] = $keyPath . ': ' . $rule . ' - bad args';
                                }
                                return false;
                            }
                        }
                        throw new \InvalidArgumentException(
                            'Args for validation rule[' . $rule . '] must be non-empty array.'
                        );
                    }
                    // Good, save for later.
                    if ($rule == 'alternativeEnum') {
                        $alternative_enum = $args;
                    } else {
                        $elements = $args;
                    }
                    break;
                default:
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if ($rules_found && isset($rules_found[$rule])) {
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {
                            // Collapse '_elements_';
                            // don't want to log deep array.
                            if (isset($ruleSet['_elements_'])) {
                                if (is_array($ruleSet['_elements_'])) {
                                    $ruleSet['_elements_'] = 'array(' . count($ruleSet['_elements_']) . ')';
                                } elseif (is_object($ruleSet['_elements_'])) {
                                    // Illegal, but anyway.
                                    $ruleSet['_elements_'] = 'object('
                                        . count(get_object_vars($ruleSet['_elements_'])) . ')';
                                } else {
                                    // Illegal, but anyway.
                                    $ruleSet['_elements_'] = '(' . gettype($ruleSet['_elements_']) . ')';
                                }
                            }
                            $logger->warning(
                                'Duplicate validation rule \'{rule_method}\''
                                . ',  declared as rule:args as well as N:rule,'
                                . ' of rule provider {rule_provider}, at key path[{key_path}].',
                                [
                                    'type' => static::LOG_TYPE,
                                    'rule_provider' => get_class($this->ruleProvider),
                                    'rule_method' => $rule,
                                    'key_path' => $keyPath,
                                    'variable' => $ruleSet,
                                ]
                            );
                            if (!$this->errUnconditionally) {
                                if ($this->recordFailure) {
                                    $this->record[] = $keyPath . ': ' . $rule . ' - duplicate rule';
                                }
                                return false;
                            }
                        }
                        throw new \InvalidArgumentException('Duplicate validation rule[' . $rule . '].');
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $this->ruleMethods)) {
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {
                            $logger->warning(
                                'Non-existent validation rule \'{rule_method}\''
                                . ' of rule provider {rule_provider}, at key path[{key_path}].',
                                [
                                    'type' => static::LOG_TYPE,
                                    'rule_provider' => get_class($this->ruleProvider),
                                    'rule_method' => $rule,
                                    'key_path' => $keyPath,
                                ]
                            );
                            if (!$this->errUnconditionally) {
                                if ($this->recordFailure) {
                                    $this->record[] = $keyPath . ': ' . $rule . ' - nonexistent rule';
                                }
                                return false;
                            }
                        }
                        throw new \InvalidArgumentException('Non-existent validation rule[' . $rule . '].');
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
        if (!$elements) {
            return true;
        }

        // Do '_elements_'.
        // Check that input var is an object or array, and get which type.
        $iterable_type = $this->ruleProvider->iterable($var);
        if (!$iterable_type) {
            // A-OK: one should - for convenience - be allowed to use
            // the '_elements_' rule, without explicitly declaring/using
            // an iterable type checker.
            if ($this->recordFailure) {
                $this->record[] = $keyPath . ': _elements_ - ' . gettype($var) . ' is not an iterable';
            }
            return false;
        }

        // Iterate array|object separately, don't want to clone object to array;
        // for performance reasons.
        switch ($iterable_type) {
            case 'array':
            case 'arrayAccess':
                $is_array = $iterable_type == 'array';
                foreach ($elements as $key => $sub_rules) {
                    if (
                        $is_array ? !array_key_exists($key, $var) : !$var->offsetExists($key)
                    ) {
                        // An element is required, unless explicitly 'optional'.
                        if (empty($sub_rules['optional']) && !in_array('optional', $sub_rules)) {
                            if ($this->recordFailure) {
                                // We don't stop on failure when recording.
                                continue;
                            }
                            return false;
                        }
                    } else {
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
                // Iterable object.
                foreach ($elements as $key => $sub_rules) {
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

        return true;
    }

    /**
     * @return array
     */
    public function getRecord() {
        return $this->record;
    }
}
