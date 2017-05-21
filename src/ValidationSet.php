<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Class Validate
 *
 * @package SimpleComplex\Filter
 */
class ValidationSet {
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
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * ValidationSet constructor.
     *
     * @param LoggerInterface|null $logger
     *  PSR-3 logger, if any.
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
    }

    /**
     * @param LoggerInterface|null
     *
     * @return static
     */
    public static function make($logger = null) {
        // Make IDE recognize child class.
        /** @var ValidationSet */
        return new static($logger);
    }

    // @todo: rename 'pattern' to 'rules'.

    // @todo: move all rules to a parent class.

    // @todo: handle non-rule flags; 'optional', 'buckets' ('children'?), 'exceptValue'|'or'|'orEnum'(?)

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
     * @param array $ruleSet
     *  A list of rules; either 'rule':[specs] or N:rule.
     *  [
     *    'integer'
     *    'range': [ 0, 2 ]
     *  ]
     *
     * @return bool
     */
    public function ruleSet($var, array $ruleSet) {
        return $this->internalRuleSet(0, $var, $ruleSet);
    }

    const NON_RULE_METHODS = array(
        '__construct',
        'make',
        'getInstance',
        'ruleSet',
    );

    protected $nonRuleMethods = array();

    /**
     * @return array
     */
    public function getNonRuleMethods() {
        if (!$this->nonRuleMethods) {
            $this->nonRuleMethods = self::NON_RULE_METHODS;
        }
        return $this->nonRuleMethods;
    }

    /**
     * Internal method necessitated by the need of an inaccessible depth argument
     * to control recursion.
     *
     * @param int $depth
     * @param mixed $var
     * @param array $ruleSet
     *
     * @return bool
     */
    protected function internalRuleSet($depth, $var, array $ruleSet) {
        static $forbidden_methods;
        if (!$forbidden_methods) {

        }



        $elements = NULL;
        foreach ($ruleSet as $k => $v) {
            // Bucket is simply the name of a rule; key is int, value is the rule.
            if (ctype_digit('' . $k)) {
                if (!$this->{$v}($var, array())) {
                    return false;
                }
            }
            // Bucket key is name of the rule,
            // value is options or specifications for the rule.
            else {
                if ($k == 'elements') {
                    $elements = $v;
                    continue;
                }
                elseif ($k == 'optional') {
                    continue;
                }
                if (!$this->{$k}($var, $v)) {
                    return false;
                }
            }
        }
        if ($elements) {
            // Prevent convoluted try-catches; only one at the top.
            if (!$depth) {
                try {
                    return $this->internalElements(++$depth, $var, $elements);
                }
                catch (\Exception $xc) {
                    //
                }
            }
            else {
                return $this->internalElements(++$depth, $var, $elements);
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
    protected function internalElements($depth, $collection, array $patterns) {
        if (is_array($collection)) {
            foreach ($patterns as $key => $pattern) {
                // @todo: use array_key_exists(); vs. null value.
                if (isset($collection[$key])) {
                    if (!$this->internalPattern($depth, $collection[$key], $pattern)) {
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
                    if (!$this->internalPattern($depth, $collection->{$key}, $pattern)) {
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

    // Catch-all.-----------------------------------------------------------------

    /**
     * @throws \LogicException
     *
     * @param string $name
     * @param array $arguments
     */
    public function __call($name, $arguments) {
        // @todo
        switch ($name) {
            case 'elements':
                // elements is a ...?
                break;
            case 'optional':
                // optional is a flag.
                break;
        }
        throw new \LogicException('Undefined validation rule[' . $name . '].');
    }

}
