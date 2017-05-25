<?php

declare(strict_types=1);
/*
 * Forwards compatility really; everybody will to this once.
 * But scalar parameter type declaration is no-go until then; coercion or TypeError(?).
 */

namespace SimpleComplex\Filter;

use SimpleComplex\Filter\Exception\InvalidArgumentException;
use SimpleComplex\Filter\Exception\OutOfRangeException;


// @todo: provide a means for recording validation failure, perhaps a 'recorder' which looks like a logger but doesn't log.

/**
 * What/how?
 * ---------
 * I. Define a list of rules, that a variable must comply with.
 * Like a flat one:
 * [ class: 'bicycle', nonEmpty: true ]
 * Or a more comprehensive one:
 * [
 *   class: 'bicycle',
 *   elements: [
 *     wheels: [
 *       'integer'
 *       range: [1, 3]
 *     ],
 *     luggage-carrier: [
 *       'optional'
 *     ],
 *     sound: [
 *       enum: [
 *         'silent',
 *         'swooshy',
 *         'clattering'
 *       ]
 *     ]
 *   ]
 * ]
 * @todo: and then what?
 *
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
 * Design considerations
 * ---------------------
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
 * @package SimpleComplex\Filter
 */
class ValidateByRules
{
    /**
     * @see GetInstanceTrait
     *
     * List of previously instantiated objects, by name.
     * @protected
     * @static
     * @var array $instances
     *
     * Reference to last instantiated instance.
     * @protected
     * @static
     * @var static $lastInstance
     *
     * Get previously instantiated object or create new.
     * @public
     * @static
     * @see GetInstanceTrait::getInstance()
     *
     * Kill class reference(s) to instance(s).
     * @public
     * @static
     * @see GetInstanceTrait::flushInstance()
     */
    use GetInstanceTrait;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'validate';

    /**
     * Rules that the rules provider doesn't (and shan't) provide.
     *
     * @todo: this var is not used, however the names are mentioned in ValidationRuleProviderInterface.
     *
     * @var array
     */
    const NON_PROVIDER_RULES = [
        'optional',
        'alternativeEnum',
        'elements',
    ];

    /**
     * The (most of the) methods of the Validate instance will be the rules available.
     *
     * @var ValidationRuleProviderInterface
     */
    protected $ruleProvider;

    /**
     * @var array
     */
    protected $ruleMethods = [];

    /**
     * Do always throw Exception on logical/runtime error, even when logger
     * available (default not).
     *
     * @var bool
     */
    protected $errUnconditionally;

    /**
     * Use Validate::challengeRules() instead of using this directly.
     *
     * Uses the logger (if any) of the rule provider.
     * But only on demand; doesn't refer it.
     *
     * @code
     * $logger = new JsonLog();
     * $validate = Validate::getInstance('', [$logger]);
     * $validateByRules = ValidateByRules::getInstance('', [
     *   $validate
     * ]);
     * @endcode
     *
     * @see Validate::challengeRules()
     *
     * @param ValidationRuleProviderInterface $ruleProvider
     *  The (most of the) methods of the Validate instance will be the rules available.
     * @param array $options
     *  (bool) errUnconditionally; default false.
     */
    public function __construct(
        ValidationRuleProviderInterface $ruleProvider,
        array $options = array(
            'errUnconditionally' => false,
        )
    ) {
        $this->ruleProvider = $ruleProvider;

        $this->errUnconditionally = !empty($options['errUnconditionally']);
    }

    /**
     * Do not call
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
     * @throws \Throwable
     *  Propagated.
     *
     * @uses get_class_methods()
     * @uses Validate::getNonRuleMethods()
     *
     * @param mixed $var
     * @param array $rules
     *  A list of rules; either 'rule':[specs] or N:'rule'.
     *  [
     *    'integer'
     *    'range': [ 0, 2 ]
     *  ]
     *
     * @return bool
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
            if (strpos($xc, __NAMESPACE__ . '\\Exception') !== 0) {
                if ($this->ruleProvider->getLogger()) {
                    // @todo
                }
            }
            throw $xc;
        }
    }

    /**
     * Recursion emergency brake.
     *
     * Ideally the depth of a rule set describing objects/arrays having nested objects/arrays
     * should limit recursion, naturally/orderly.
     * But circular references within the rule set - or a programmatic error in this library
     * - could (without this hardcoded limit) result in perpetual recursion.
     *
     * @var int
     */
    const RECURSION_LIMIT = 10;

    /**
     * Internal method to accommodate an inaccessible depth argument, to control/limit recursion.
     *
     * @recursive
     *
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     *
     * @param int $depth
     * @param string $keyPath
     * @param mixed $var
     * @param array $ruleSet
     *
     * @return bool
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
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $rules_found = $alternativeEnum = $elements = [];
        foreach ($ruleSet as $k => $v) {
            if (ctype_digit('' . $k)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $v;
                $args = null;
            } else {
                // Bucket key is name of the rule,
                // value is arguments for the rule method.
                $rule = $k;
                $args = $v;
            }
            switch ($rule) {
                case 'optional':
                    // Do nothing, ignore here. Only used when working on 'elements'.
                    break;
                case 'alternativeEnum':
                case 'elements':
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
                            // Important.
                            return false;
                        }
                        throw new \InvalidArgumentException(
                            'Args for validation rule[' . $rule . '] must be non-empty array.'
                        );
                    }
                    // Good, save for later.
                    if ($rule == 'alternativeEnum') {
                        $alternativeEnum = $args;
                    } else {
                        $elements = $args;
                    }
                    break;
                default:
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if ($rules_found && isset($rules_found[$rule])) {
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {
                            // Collapse 'elements'; don't want to log deep array.
                            if (isset($ruleSet['elements'])) {
                                if (is_array($ruleSet['elements'])) {
                                    $ruleSet['elements'] = 'array(' . count($ruleSet['elements']) . ')';
                                } elseif (is_object($ruleSet['elements'])) {
                                    // Illegal, but anyway.
                                    $ruleSet['elements'] = 'object('
                                        . count(get_object_vars($ruleSet['elements'])) . ')';
                                } else {
                                    // Illegal, but anyway.
                                    $ruleSet['elements'] = '(' . gettype($ruleSet['elements']) . ')';
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
                                return false;
                            }
                        }
                        throw new \InvalidArgumentException('Duplicate validation rule[' . $rule . '].');
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $this->$this->ruleMethods)) {
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
        foreach ($rules_found as $rule => $args) {
            // Don't use call_user_func_array() when not needed; performance.
            // And we expect more boolean trues than arrays (few Validate methods take secondary args).
            if (!$args || $args === true || !is_array($args)) {
                if (!$this->ruleProvider->{$rule}($var)) {
                    $failed = true;
                }
            } else {
                $n_args = count($args);
                switch ($n_args) {
                    case 1:
                        if (!$this->ruleProvider->{$rule}($var, reset($args))) {
                            $failed = true;
                        }
                        break;
                    case 2:
                        if (!$this->ruleProvider->{$rule}($var, reset($args), next($args))) {
                            $failed = true;
                        }
                        break;
                    case 3:
                        if (!$this->ruleProvider->{$rule}($var, reset($args), next($args), next($args))) {
                            $failed = true;
                        }
                        break;
                    case 4:
                        if (!$this->ruleProvider->{$rule}($var, reset($args), next($args), next($args), next($args))) {
                            $failed = true;
                        }
                        break;
                    default:
                        // Too many args.
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {
                            $logger->warning(
                                'Too many arguments[{n_arguments}] for validation rule \'{rule_method}\''
                                . ' of rule provider {rule_provider}, at key path[{key_path}].',
                                [
                                    'type' => static::LOG_TYPE,
                                    'rule_provider' => get_class($this->ruleProvider),
                                    'rule_method' => $rule,
                                    'n_arguments' => $n_args,
                                    'key_path' => $keyPath,
                                ]
                            );
                            if (!$this->errUnconditionally) {
                                return false;
                            }
                        }
                        throw new InvalidArgumentException(
                            'Too many arguments for validation rule[' . $rule . '].'
                        );
                }
            }
            if ($failed) {
                break;
            }
        }

        if ($failed) {
            // Matches one of a list of alternative (scalar|null) values?
            if ($alternativeEnum && $this->ruleProvider->enum($var, $alternativeEnum)) {
                return true;
            }
            return false;
        }

        // Didn't fail.
        if (!$elements) {
            return true;
        }

        // Do 'elements'.
        // Check that it's an object or array, and get which type.
        $collection_type = $this->ruleProvider->collection($var);
        if (!$collection_type) {
            // A-OK: one should - for convenience - be allowed to use the 'elements' rule,
            // without explicitly declaring/using a collection type checker.
            return false;
        }

        // Iterate array|object separately, don't want to clone object to array; for performance reasons.
        if ($collection_type == 'array') {
            foreach ($elements as $key => $subRuleSet) {
                if (!array_key_exists($key, $var)) {
                    // An element is required, unless explicitly 'optional'.
                    if (empty($subRuleSet['optional']) && !in_array('optional', $subRuleSet)) {
                        return false;
                    }
                } else {
                    // Recursion.
                    if (!$this->internalChallenge($depth + 1, $keyPath . '[' . $key . ']', $var[$key], $subRuleSet)) {
                        return false;
                    }
                }
            }
        } else {
            // Object.
            foreach ($elements as $key => $subRuleSet) {
                if (!property_exists($var, $key)) {
                    // An element is required, unless explicitly 'optional'.
                    if (empty($subRuleSet['optional']) && !in_array('optional', $subRuleSet)) {
                        return false;
                    }
                } else {
                    // Recursion.
                    if (!$this->internalChallenge($depth + 1, $keyPath . '->' . $key, $var->{$key}, $subRuleSet)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
