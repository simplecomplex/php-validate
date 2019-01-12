<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Exception\BadMethodCallException;
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
class ValidateAgainstRuleSet
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * using a specific rule provider,
     * no matter which parent/child class the method was/is called on.
     *
     * @var ValidateAgainstRuleSet[]
     */
    protected static $instanceByValidateClass = [];

    /**
     * First object instantiated via this method, using that rule provider,
     * disregarding which ValidateAgainstRuleSet class called on.
     *
     * Does not allow constructor $options argument because that would affect
     * instance state, voiding the warranty that the requested and referred
     * returned instances are effectively identical.
     *
     * @see Validate::challenge()
     *
     * @param RuleProviderInterface $ruleProvider
     *
     * @return ValidateAgainstRuleSet
     *      static, really, but IDE might not resolve that.
     *
     * @throws BadMethodCallException
     *      If passed more than one argument.
     */
    public static function getInstance(RuleProviderInterface $ruleProvider)
    {
        if (func_num_args() > 1) {
            throw new BadMethodCallException(
                'Method allows only one argument (a rule provider), passing options would void warranty'
                . ' that requested and returned instance are effectvely identical, saw secondary argument type['
                . Utils::getType(func_get_arg(1)) . '].'
            );
        }
        $provider_class = get_class($ruleProvider);
        return static::$instanceByValidateClass[$provider_class] ??
            (static::$instanceByValidateClass[$provider_class] = new static($ruleProvider));
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
        'allowNull',
        'alternativeEnum',
        'tableElements',
        'listItems',
    ];


    /**
     * Instance vars are not allowed to have state
     * -------------------------------------------
     * unless related to recording.
     * Because all Validate instances reuse the same instance of this class,
     * for every call to Validate::challenge().
     *
     * Vars ruleProvider and ruleMethods do not infringe that principle.
     *
     * @see Validate::challenge()
     * @see Validate::challengeRecording()
     */

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
     *      @var bool recordFailure  Default: false.
     * }
     */
    public function __construct(RuleProviderInterface $ruleProvider, array $options = []) {
        $this->ruleProvider = $ruleProvider;
        $this->recordFailure = !empty($options['recordFailure']);
    }

    /**
     * Use Validate::challenge() instead of this.
     *
     * @see Validate::challenge()
     * @see Validate::challengeRecording()
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
     * @param string $keyPath
     *      Name of element to validate, or key path to it.
     *
     * @return bool
     *
     * @throws \TypeError
     *      Arg rules not array|object.
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($subject, $ruleSet, string $keyPath = 'root')
    {
        // Init, really.
        // List rule methods made available by the rule provider.
        $provider_info = null;
        if (!$this->ruleMethods) {
            $provider_info = new RuleProviderInfo($this->ruleProvider);
            $this->ruleMethods = $provider_info->ruleMethods;
        }

        if ($ruleSet instanceof ValidationRuleSet) {
            return $this->internalChallenge(0, $keyPath, $subject, $ruleSet);
        } elseif (!is_array($ruleSet) && !is_object($ruleSet)) {
            throw new \TypeError(
                'Arg rules type[' . Utils::getType($ruleSet) . '] is not ValidationRuleSet|array|object.'
            );
        }
        // Convert non-ValidationRuleSet arg $ruleSet to ValidationRuleSet,
        // to secure checks.
        return $this->internalChallenge(
            0,
            $keyPath,
            $subject,
            new ValidationRuleSet($ruleSet, $provider_info ?? new RuleProviderInfo($this->ruleProvider))
        );
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
     * @param ValidationRuleSet $ruleSet
     *
     * @return bool
     *
     * @throws InvalidRuleException
     * @throws OutOfRangeException
     */
    protected function internalChallenge($depth, $keyPath, $subject, ValidationRuleSet $ruleSet)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit[' . static::RECURSION_LIMIT . '].'
            );
        }

        $rules_found = [];
        $allowNull = false;
        $alternative_enum = $table_elements = $list_items = null;
        foreach ($ruleSet as $ruleKey => $ruleValue) {
            switch ($ruleKey) {
                case 'optional':
                    // Do nothing, ignore here.
                    // Only used when working on tableElements|listItems.
                    break;
                case 'allowNull':
                    $allowNull = true;
                    break;
                case 'alternativeEnum':
                    // No need to check for falsy nor non-array;
                    // ValidationRuleSet do that.
                    $alternative_enum = $ruleValue;
                    break;
                case 'tableElements':
                    // No need to check for type; ValidationRuleSet do that,
                    // and makes it object.
                    $table_elements = $ruleValue;
                    break;
                case 'listItems':
                    // No need to check for type; ValidationRuleSet do that,
                    // and makes it object.
                    $list_items = $ruleValue;
                    break;
                default:
                    // No need to check for dupes; ValidationRuleSet does that.
                    // But do check for rule method existance, because we might
                    // be using a different rule provider now.
                    if (!in_array($ruleKey, $this->ruleMethods)) {
                        throw new InvalidRuleException('Non-existent validation rule[' . $ruleKey . '].');
                    }
                    $rules_found[$ruleKey] = $ruleValue;
            }
        }

        // Roll it.
        $failed = false;
        $record = [];

        if ($subject === null) {
            if ($allowNull) {
                return true;
            }
            // If enum rule, then that may allow null.
            if (!property_exists($ruleSet, 'enum')) {
                // Continue to alternativeEnum check.
                $failed = true;
            }
            // Otherwise let enum rule in ruleSet iteration do validation.
        }

        if (!$failed) {
            foreach ($rules_found as $rule => $args) {
                // We expect more boolean trues than arrays;
                // few Validate methods take secondary args.
                if ($args === true) {
                    if (!$this->ruleProvider->{$rule}($subject)) {
                        $failed = true;
                        if ($this->recordFailure) {
                            $record[] = $rule . '(*)';
                        }
                        break;
                    }
                } elseif ($rule == 'enum') {
                    // Use own pre-checked enum() because ValidationRuleSet checks
                    // that all allowed values are scalar|null.
                    if (!$this->preCheckedEnum($subject, $args[0])) {
                        $failed = true;
                        if ($this->recordFailure) {
                            $record[] = $rule . '(*)';
                        }
                        break;
                    }
                }
                // No need to check for falsy nor non-array $args;
                // ValidationRuleSet do that.
                elseif (!$this->ruleProvider->{$rule}($subject, ...$args)) {
                    $failed = true;
                    if ($this->recordFailure) {
                        if ($args && is_array($args)) {
                            $tmp = [];
                            foreach ($args as $arg) {
                                $arg_type = Utils::getType($arg);
                                switch ($arg_type) {
                                    case 'boolean':
                                        $tmp[] = $arg ? 'true' : 'false';
                                        break;
                                    case 'integer':
                                    case 'float':
                                        $tmp[] = $arg;
                                        break;
                                    case 'string':
                                        $tmp[] = "'" . $arg . "'";
                                        break;
                                    default:
                                        $tmp[] = $arg_type;
                                }
                            }
                            $record[] = $rule . '(*, ' . join(', ', $tmp) . ')';
                            unset($tmp, $arg, $arg_type);
                        }
                        else {
                            $record[] = $rule . '(*)';
                        }
                    }
                    break;
                }
            }
        }

        if ($failed) {
            // Matches one of a list of alternative (scalar|null) values?
            if ($alternative_enum) {
                // Use own pre-checked enum() because ValidationRuleSet checks
                // that all allowed values are scalar|null.
                if ($this->preCheckedEnum($subject, $alternative_enum)) {
                    return true;
                }
                if ($this->recordFailure) {
                    $v = null;
                    if (is_scalar($subject)) {
                        $v = !is_string($subject) || strlen($subject) <= 50 ? $subject :
                            (substr($subject, 50) . '...(truncated)');
                    }
                    $this->record[] = $keyPath . ': ' . join(', ', $record) . ', alternativeEnum(*, ...) - saw type '
                        . Utils::getType($subject) . ($v === null ? '' : (' value ' . $v));
                }
                return false;
            }
            if ($this->recordFailure) {
                $v = null;
                if (is_scalar($subject)) {
                    $v = !is_string($subject) || strlen($subject) <= 50 ? $subject :
                        (substr($subject, 50) . '...(truncated)');
                }
                $this->record[] = $keyPath . ': ' . join(', ', $record) . ' - saw type ' . Utils::getType($subject)
                    . ($v === null ? '' : (' value ' . $v));
            }
            return false;
        }

        // Didn't fail.
        if (!$table_elements && !$list_items) {
            return true;
        }

        // Prepare for 'tableElements' and/or 'listItems'.
        // Check that subject is a loopable container.
        $container_type = $this->ruleProvider->loopable($subject);
        if (!$container_type) {
            // A-OK: one should - for convenience - be allowed to use
            // the 'tableElements' and/or 'list_item_prototype' rule, without
            // explicitly defining/using a container type checker.
            if ($this->recordFailure) {
                $this->record[] = $keyPath . ': tableElements - ' . Utils::getType($subject)
                    . ' is not a loopable container';
            }
            return false;
        }

        // tableElements combined with listItems is allowed.
        // Relevant for a container derived from XML, which allows hash table
        // elements and list items within the same container (XML sucks ;-).
        // To prevent collision (repeated validation of elements) we filter
        // declared tableElements out of list validation.
        $item_list_skip_keys = [];

        if ($table_elements) {
            $subject_keys = $specified_keys = [];
            $exclusive = $whitelist = $blacklist = false;
            if (!empty($table_elements->exclusive)) {
                $exclusive = true;
                $specified_keys = array_keys(get_object_vars($table_elements->rulesByElements));
            } elseif (!empty($table_elements->whitelist)) {
                $whitelist = true;
                $specified_keys = array_keys(get_object_vars($table_elements->rulesByElements));
            } elseif (!empty($table_elements->blacklist)) {
                $blacklist = true;
            }
            if ($exclusive || $whitelist || $blacklist) {
                switch ($container_type) {
                    case 'array':
                        $subject_keys = array_keys($subject);
                        break;
                    /**
                     * Traversable ArrayAccess.
                     * @see Validate::container()
                     */
                    case 'arrayAccess':
                        if ($subject instanceof \ArrayObject || $subject instanceof \ArrayIterator) {
                            $subject_keys = array_keys($subject->getArrayCopy());
                        } else {
                            // ArrayAccess itself specifies no means of getting
                            // keys. Unlikely meeting such class, but anyway.
                            foreach ($subject as $key => $ignore) {
                                $subject_keys[] = $key;
                            }
                        }
                        break;
                    default:
                        $subject_keys = array_keys(get_object_vars($subject));
                }
                if ($exclusive) {
                    if (($illegal_keys = array_diff($subject_keys, $specified_keys))) {
                        if ($this->recordFailure) {
                            $this->record[] = $keyPath . ': tableElements exclusive - subject has key(s)'
                                . ' not specified by elementsByRules, key(s) \''
                                . join('\', \'', $illegal_keys) . '\'';
                            // Don't stop on failure when recording.
                        } else {
                            return false;
                        }
                    }
                } elseif ($whitelist) {
                    if (($illegal_keys = array_diff($subject_keys, $specified_keys, $table_elements->whitelist))) {
                        if ($this->recordFailure) {
                            $this->record[] = $keyPath . ': tableElements whitelist - subject has key(s)'
                                . ' not specified by elementsByRules nor whitelist, key(s) \''
                                . join('\', \'', $illegal_keys) . '\'';
                            // Don't stop on failure when recording.
                        } else {
                            return false;
                        }
                    }
                } elseif (($illegal_keys = array_intersect($table_elements->blacklist, $subject_keys))) {
                    if ($this->recordFailure) {
                        $this->record[] = $keyPath . ': tableElements blacklist - subject has blacklisted key(s) \''
                            . join('\', \'', $illegal_keys) . '\'';
                        // Don't stop on failure when recording.
                    } else {
                        return false;
                    }
                }
            }
            unset($subject_keys, $specified_keys, $illegal_keys);
            // Iterate array|object separately, don't want to clone object
            // to array (nor vice versa).
            switch ($container_type) {
                case 'array':
                case 'arrayAccess':
                    $is_array = $container_type == 'array';
                    foreach ($table_elements->rulesByElements as $key => $element_rule_set) {
                        if ($is_array ? !array_key_exists($key, $subject) : !$subject->offsetExists($key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($element_rule_set->optional)) {
                                if ($this->recordFailure) {
                                    $this->record[] = $keyPath . ': tableElements - non-optional bucket '
                                        . $key . ' doesn\'t exist';
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $item_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge(
                                $depth + 1, $keyPath . '[' . $key . ']', $subject[$key], $element_rule_set)
                            ) {
                                if ($this->recordFailure) {
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        }
                    }
                    break;
                default:
                    // Traversable, object.
                    foreach ($table_elements->rulesByElements as $key => $element_rule_set) {
                        if (!property_exists($subject, $key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($element_rule_set->optional)) {
                                if ($this->recordFailure) {
                                    $this->record[] = $keyPath . ': tableElements - non-optional bucket '
                                        . $key . ' doesn\'t exist';
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $item_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge(
                                $depth + 1, $keyPath . '->' . $key, $subject->{$key}, $element_rule_set)
                            ) {
                                if ($this->recordFailure) {
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        }
                    }
            }
        }

        if ($list_items) {
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
            $occurrence = 0;
            foreach ($subject as $index => $item) {
                if (!$item_list_skip_keys || !in_array($index, $item_list_skip_keys, true)) {
                    ++$occurrence;
                    // Recursion.
                    if (!$this->internalChallenge(
                        $depth + 1, $keyPath . $prefix . $index . $suffix, $item, $list_items->itemRules)
                    ) {
                        if ($this->recordFailure) {
                            // Don't stop on failure when recording.
                            continue;
                        }
                        return false;
                    }
                }
            }
            $minOccur = $list_items->minOccur ?? 0;
            if ($minOccur && $occurrence < $minOccur) {
                if ($this->recordFailure) {
                    $this->record[] = $keyPath . ': listItems - saw less instances ' . $occurrence
                        . ' than minOccur ' . $minOccur;
                    // Don't stop on failure when recording.
                } else {
                    return false;
                }
            }
            $maxOccur = $list_items->maxOccur ?? 0;
            if ($maxOccur && $occurrence > $maxOccur) {
                if ($this->recordFailure) {
                    $this->record[] = $keyPath . ': listItems - saw more instances ' . $occurrence
                        . ' than maxOccur ' . $maxOccur;
                    // Don't stop on failure when recording.
                } else {
                    return false;
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

    /**
     * Enum rule method which doesn't check that all allowed values
     * are scalar|null; ValidationRuleSet checks that.
     *
     *
     *
     * @see Validate::enum()
     *
     * @param mixed $subject
     * @param array $allowedValues
     *
     * @return bool
     */
    protected function preCheckedEnum($subject, array $allowedValues) : bool
    {
        if ($subject !== null && !is_scalar($subject)) {
            return false;
        }
        foreach ($allowedValues as $allowed) {
            if ($subject === $allowed) {
                return true;
            }
        }
        return false;
    }
}
