<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Class Validate
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
    protected $providerNonRuleMethods = [];

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
        $this->providerNonRuleMethods = $ruleProvider->getNonRuleMethods();
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
                    'Stopped recursive validation by rule-set at limit {recursion_limit}, value key path[{key_path}].',
                    [
                        'type' => 'validation',
                        'recursion_limit' => static::RECURSION_LIMIT,
                        'key_path' => $keyPath,
                    ]
                );
            }
            return false;
        }

        $failed = $optional = $fallbackEnum = $elements = false;
        foreach ($ruleSet as $k => $v) {
            // Bucket is simply the name of a rule; key is int, value is the rule.
            if (ctype_digit('' . $k)) {
                $rule = $v;
                $args = null;
            }
            // Bucket key is name of the rule,
            // value is arguments for the rule method.
            else {
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
                    break;
                case 'elements':
                    if ($args) {
                        // Save for later.
                        $elements = $args;
                    }
                    break;
                default:
            }
        }

        if ($failed) {
            if ($optional) {
                return true;
            }
            if ($fallbackEnum) {


            }
        }



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
            }
            else {
                return $this->elements(++$depth, $var, $elements);
            }
        }
        return true;
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
                }
                elseif (empty($pattern['optional'])) {
                    return false;
                }
            }
        }
        elseif (is_object($collection)) {
            foreach ($patterns as $key => $pattern) {
                // @todo: use property_exists(); vs. null value.
                if (isset($collection->{$key})) {
                    if (!$this->internalChallenge($depth, $collection->{$key}, $pattern)) {
                        return false;
                    }
                }
                elseif (empty($pattern['optional'])) {
                    return false;
                }
            }
        }
        else {
            return false;
        }
        return true;
    }
}
