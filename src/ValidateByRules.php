<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * When listing secondary rule method arguments
 *
 *
 * WHEN A RULE TAKES/REDQUIRES SECONDARY ARGUMENTS
 * The sequence of buckets is essential, keys - whether numeric or associative
 * - are ignored. They will be accessed/used via reset(), next()...
 *
 *
 *
 * @package SimpleComplex\Filter
 */
class ValidateByRules {
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
     * @var static|null $lastInstance
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
    const LOG_TYPE = 'validation';

    /**
     * Rules that the rules provider doesn't (and shan't) provide.
     *
     * @var array
     */
    const NON_PROVIDER_RULES = [
        'optional',
        'fallbackEnum',
        'elements',
    ];

    /**
     * The (most of the) methods of the Validate instance will be the rules available.
     *
     * @var ValidationRuleProviderInterface|null
     */
    protected $ruleProvider;

    /**
     * @var array
     */
    protected $ruleMethods = [];

    /**
     * Uses the logger (if any) of the rules provider.
     * But only on demand; doesn't refer it.
     *
     * @code
     * $logger = new JsonLog();
     * $validate = Validate::getInstance('', $logger);
     * $validateByRules = ValidateByRules::getInstance('', [
     *   $validate
     * ]);
     * @endcode
     *
     * @param ValidationRuleProviderInterface $ruleProvider
     *  The (most of the) methods of the Validate instance will be the rules available.
     */
    public function __construct(ValidationRuleProviderInterface $ruleProvider) {
        $this->ruleProvider = $ruleProvider;
    }

    /**
     * @param ValidationRuleProviderInterface $ruleProvider
     *
     * @return static
     */
    public static function make(ValidationRuleProviderInterface $ruleProvider) {
        // Make IDE recognize child class.
        /** @var ValidateByRules */
        return new static($ruleProvider);
    }

    /**
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
    public function challenge($var, array $rules) {
        // Init, really.
        if (!$this->ruleMethods) {
            $this->ruleMethods = array_diff(
                get_class_methods(get_class($this->ruleProvider)),
                $this->ruleProvider->getNonRuleMethods()
            );
        }


        // @todo: Use library specific exception types.
        try {
            //
        } catch (\Exception $xc) {
            //

            // @todo: non-library exception type: log (as warning) and rethrow.

            // @todo: in-library exception type: don't catch, let propagate.
        }

        return $this->internalChallenge(0, '', $var, $rules);
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
     * @param int $depth
     * @param string $keyPath
     * @param mixed $var
     * @param array $ruleSet
     *
     * @return bool
     */
    protected function internalChallenge($depth, $keyPath, $var, $ruleSet) {
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
                // Important.
                return false;
            }
            // @todo: SimpleComplex\Filter\RecursionException.
            throw new \OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $failed = $optional = $typeFailed = $fallbackEnum = $isCollection = $elements = false;
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
                    // Save for later.
                    $optional = true;
                    break;
                case 'fallbackEnum':
                    if ($args) {
                        // Save for later.
                        $fallbackEnum = $args;
                    }
                    // Otherwise (falsy) ignore.
                    break;
                case 'elements':
                    if ($args) {
                        // Save for later.
                        $elements = $args;
                    }
                    // Otherwise (falsy) ignore.
                    break;
                default:
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
                            // Important.
                            return false;
                        }
                        throw new \LogicException('Non-existent validation rule[' . $rule . '].');
                    }

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
                                    // Important.
                                    return false;
                                }
                                throw new \InvalidArgumentException(
                                    'Too many arguments for validation rule[' . $rule . '].'
                                );
                        }
                    }
            }
            if ($failed) {
                // Break loop.
                break;
            }
        }

        if ($failed) {
            // @todo: 'optional' belongs int the 'elements' method, not here.
            if ($optional) {
                return true;
            }
            // @todo: 'fallbackEnum is only relevant if the var is ::empty().
            if ($fallbackEnum) {
                return $this->ruleProvider->enum($var, $fallbackEnum);
            }
        } else {
            // 'elements' is only relevant if (the object|array) didn't fail for other reason.
            if ($elements) {
                // Prevent convoluted try-catches; only one at the top.
                if (!$depth) {
                    try {
                        return $this->elements(++$depth, $var, $elements);
                    }
                    catch (\Exception $xc) {
                        $logger = $this->ruleProvider->getLogger();
                        if ($logger) {

                        }
                    }
                } else {
                    return $this->elements(++$depth, $var, $elements);
                }
            }
        }

        return $failed;
    }


    /**
     * Recursive.
     *
     * @recursive
     *
     * @param array|object $collection
     * @param array $patterns
     *
     * @return bool
     */
    protected function elements($depth, $collection, array $patterns) {
        if (is_array($collection)) {
            foreach ($patterns as $key => $pattern) {
                // @todo: use array_key_exists(); vs. null value.
                if (isset($collection[$key])) {
                    if (!$this->internalChallenge($depth, $collection[$key], $pattern)) {
                        return false;
                    }
                } elseif (empty($pattern['optional'])) {
                    return false;
                }
            }
        } elseif (is_object($collection)) {
            foreach ($patterns as $key => $pattern) {
                // @todo: use property_exists(); vs. null value.
                if (isset($collection->{$key})) {
                    if (!$this->internalChallenge($depth, $collection->{$key}, $pattern)) {
                        return false;
                    }
                } elseif (empty($pattern['optional'])) {
                    return false;
                }
            }
        } else {
            return false;
        }
        return true;
    }
}
