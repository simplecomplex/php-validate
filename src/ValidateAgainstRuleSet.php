<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

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
                . Helper::getType(func_get_arg(1)) . '].'
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
     * @see ValidationRuleSet::RECURSION_LIMIT
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
        'alternativeRuleSet',
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
     * Stops validation on first failure,
     * except if truthy options recordFailure.
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
        $this->ruleMethods = $ruleProvider->getRuleMethods();
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
     * $validate->challenge($some_input, [
     *     'integer',
     *     'range' => [
     *         0,
     *         2
     *     ]
     * ]);
     * @endcode
     *
     * @param mixed $subject
     * @param ValidationRuleSet|object|array $ruleSet
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
        if ($ruleSet instanceof ValidationRuleSet) {
            return $this->internalChallenge($subject, $ruleSet, 0, $keyPath);
        }
        elseif (is_object($ruleSet) && !is_array($ruleSet)) {
            throw new \TypeError(
                'Arg rules type[' . Helper::getType($ruleSet) . '] is not ValidationRuleSet|array|object.'
            );
        }
        // Convert non-ValidationRuleSet arg $ruleSet to ValidationRuleSet,
        // to secure checks.
        return $this->internalChallenge(
            $subject,
            new ValidationRuleSet(
                is_object($ruleSet) ? $ruleSet : ((object) $ruleSet),
                $this->ruleProvider,
                0,
                $keyPath
            ),
            0,
            $keyPath
        );
    }

    /**
     * Internal method to accommodate an inaccessible depth argument,
     * to control/limit recursion.
     *
     * @recursive
     *
     * @param mixed $subject
     * @param ValidationRuleSet $ruleSet
     * @param int $depth
     * @param string $keyPath
     *
     * @return bool
     *
     * @throws InvalidRuleException
     * @throws OutOfRangeException
     */
    protected function internalChallenge($subject, ValidationRuleSet $ruleSet, $depth, $keyPath)
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit['
                . static::RECURSION_LIMIT . '], at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $rules_found = [];
        $allowNull = false;
        $enum = $alternativeEnum =
            $alternativeRuleSet =
            $tableElements = $listItems = null;
        foreach ($ruleSet as $ruleKey => $ruleValue) {
            switch ($ruleKey) {
                case 'optional':
                    // Do nothing, ignore here.
                    // Only used when working on tableElements|listItems.
                    break;
                case 'allowNull':
                    $allowNull = true;
                    break;
                case 'enum':
                    // Support definition as nested array, because enum used to require
                    // (overly formalistic) that the allowed values array was nested;
                    // since the allowed values array is the second argument to be passed
                    // to the enum() method.
                    /** @var array $enum */
                    $enum = is_array(reset($ruleValue)) ? reset($ruleValue) : $ruleValue;
                    break;
                case 'alternativeEnum':
                    // Like 'enum'; backwards compatibility.
                    /** @var array $alternativeEnum */
                    $alternativeEnum = is_array(reset($ruleValue)) ? reset($ruleValue) : $ruleValue;
                    break;
                case 'alternativeRuleSet':
                    /** @var ValidationRuleSet $alternativeRuleSet */
                    $alternativeRuleSet = $ruleValue;
                    break;
                case 'tableElements':
                    $tableElements = $ruleValue;
                    break;
                case 'listItems':
                    // No need to check for type; ValidationRuleSet do that,
                    // and makes it object.
                    $listItems = $ruleValue;
                    break;
                default:
                    if (!in_array($ruleKey, $this->ruleMethods)) {
                        throw new InvalidRuleException(
                            'Unknown validation rule[' . $ruleKey . '], at (' . $depth . ') ' . $keyPath . '.'
                        );
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
            if (!$enum) {
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
                            $record[] = $this->recordCurrent($rule);
                        }
                        break;
                    }
                }
                elseif ($rule == 'enum') {
                    // Use own pre-checked enum() because ValidationRuleSet checks
                    // that all allowed values are scalar|null.
                    if (!$this->enum($subject, $enum)) {
                        $failed = true;
                        if ($this->recordFailure) {
                            $record[] = $this->recordCurrent($rule, $enum);
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
                                $arg_type = Helper::getType($arg);
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
                                        $tmp[] = '(' . $arg_type . ')';
                                }
                            }
                            $record[] = $this->recordCurrent($rule, $args);
                        }
                        else {
                            $record[] = $this->recordCurrent($rule);
                        }
                    }
                    break;
                }
            }
        }

        if ($failed) {
            // Matches one of a list of alternative (scalar|null) values?
            if ($alternativeEnum) {
                // Use own pre-checked enum() because ValidationRuleSet checks
                // that all allowed values are scalar|null.
                if ($this->enum($subject, $alternativeEnum)) {
                    return true;
                }
                if (!$alternativeRuleSet) {
                    if ($this->recordFailure) {
                        $record[] = $this->recordCurrent('alternativeEnum', $alternativeEnum);
                        $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
                    }
                    return false;
                }
            }
            if ($alternativeRuleSet) {
                return $this->internalChallenge($subject, $alternativeRuleSet, $depth + 1, $keyPath);
            }
            if ($this->recordFailure) {
                $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
            }
            return false;
        }

        // Didn't fail.
        if (!$tableElements && !$listItems) {
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
                $this->recordCumulative(
                    $subject, $depth, $keyPath, ($tableElements ? 'tableElements' : 'listItems') . '.loopable'
                );
            }
            return false;
        }

        // @todo: tableElements, listItems get checked after alternativeRuleSet.
        // @todo: tableElements, listItems combined is legal, but if tableElements pass then listItems won't be used/checked.

        // tableElements combined with listItems is allowed.
        // Relevant for a container derived from XML, which allows hash table
        // elements and list items within the same container (XML sucks ;-).
        // To prevent collision (repeated validation of elements) we filter
        // declared tableElements out of list validation.
        $item_list_skip_keys = [];

        if ($tableElements) {
            $subject_keys = $specified_keys = [];
            $exclusive = $whitelist = $blacklist = false;
            if (!empty($tableElements->exclusive)) {
                $exclusive = true;
                $specified_keys = array_keys(get_object_vars($tableElements->rulesByElements));
            } elseif (!empty($tableElements->whitelist)) {
                $whitelist = true;
                $specified_keys = array_keys(get_object_vars($tableElements->rulesByElements));
            } elseif (!empty($tableElements->blacklist)) {
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
                            $this->record[] = '(' . $depth . ') ' . $keyPath
                                . ': tableElements exclusive - subject has key(s)'
                                . ' not specified by elementsByRules, key(s) \''
                                . join('\', \'', $illegal_keys) . '\'';
                            // Don't stop on failure when recording.
                        } else {
                            return false;
                        }
                    }
                } elseif ($whitelist) {
                    if (($illegal_keys = array_diff($subject_keys, $specified_keys, $tableElements->whitelist))) {
                        if ($this->recordFailure) {
                            $this->record[] = '(' . $depth . ') ' . $keyPath
                                . ': tableElements whitelist - subject has key(s)'
                                . ' not specified by elementsByRules nor whitelist, key(s) \''
                                . join('\', \'', $illegal_keys) . '\'';
                            // Don't stop on failure when recording.
                        } else {
                            return false;
                        }
                    }
                } elseif (($illegal_keys = array_intersect($tableElements->blacklist, $subject_keys))) {
                    if ($this->recordFailure) {
                        $this->record[] = '(' . $depth . ') ' . $keyPath
                            . ': tableElements blacklist - subject has blacklisted key(s) \''
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
                    foreach ($tableElements->rulesByElements as $key => $element_rule_set) {
                        if ($is_array ? !array_key_exists($key, $subject) : !$subject->offsetExists($key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($element_rule_set->optional)) {
                                if ($this->recordFailure) {
                                    $this->record[] = '(' . $depth . ') ' . $keyPath
                                        . ': tableElements - non-optional bucket ' . $key . ' doesn\'t exist';
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $item_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge(
                                $subject[$key], $element_rule_set, $depth + 1, $keyPath . '[' . $key . ']')
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
                    foreach ($tableElements->rulesByElements as $key => $element_rule_set) {
                        if (!property_exists($subject, $key)) {
                            // An element is required, unless explicitly 'optional'.
                            if (empty($element_rule_set->optional)) {
                                if ($this->recordFailure) {
                                    $this->record[] = '(' . $depth . ') ' . $keyPath
                                        . ': tableElements - non-optional bucket ' . $key . ' doesn\'t exist';
                                    // Don't stop on failure when recording.
                                    continue;
                                }
                                return false;
                            }
                        } else {
                            $item_list_skip_keys[] = $key;
                            // Recursion.
                            if (!$this->internalChallenge(
                                $subject->{$key}, $element_rule_set, $depth + 1, $keyPath . '->' . $key)
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

        // @todo: tableElements, listItems are allowed combined in validation ruleset.
        // @todo: but subject must only match one of them.

        elseif ($listItems) {
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
                        $depth + 1, $keyPath . $prefix . $index . $suffix, $item, $listItems->itemRules)
                    ) {
                        if ($this->recordFailure) {
                            // Don't stop on failure when recording.
                            continue;
                        }
                        return false;
                    }
                }
            }
            $minOccur = $listItems->minOccur ?? 0;
            if ($minOccur && $occurrence < $minOccur) {
                if ($this->recordFailure) {
                    $this->record[] = '(' . $depth . ') ' . $keyPath
                        . ': listItems - saw less instances ' . $occurrence . ' than minOccur ' . $minOccur;
                    // Don't stop on failure when recording.
                } else {
                    return false;
                }
            }
            $maxOccur = $listItems->maxOccur ?? 0;
            if ($maxOccur && $occurrence > $maxOccur) {
                if ($this->recordFailure) {
                    $this->record[] = '(' . $depth . ') ' . $keyPath . ': listItems - saw more instances '
                        . $occurrence . ' than maxOccur ' . $maxOccur;
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
     * @see Validate::enum()
     * @see ValidationRuleSet::enum()
     *
     * @param mixed $subject
     * @param array $allowedValues
     *
     * @return bool
     */
    protected function enum($subject, array $allowedValues) : bool
    {
        if ($subject !== null && !is_scalar($subject)) {
            return false;
        }
        return in_array($subject, $allowedValues, true);
    }

    /**
     * @todo
     *
     * @param object|array $subject
     * @param TableElements $tableElements
     * @param int $depth
     * @param string $keyPath
     *
     * @return bool
     */
    protected function tableElements($subject, TableElements $tableElements, int $depth, string $keyPath) : bool
    {
        $failed = false;
        $record = [];
        $keys_found = [];
        foreach ($subject as $key => $value) {
            if (!in_array($key, $tableElements->keys, true)) {
                if ($tableElements->exclusive) {
                    if ($this->recordFailure) {
                        $failed = true;
                        $record[] = $this->recordCurrent(
                            'tableElements excludes key[' . $key . ']'
                        );
                    }
                    else {
                        return false;
                    }
                }
                elseif ($tableElements->whitelist) {
                    if (!in_array($key, $tableElements->whitelist, true)) {
                        if ($this->recordFailure) {
                            $failed = true;
                            $record[] = $this->recordCurrent('tableElements doesn\'t whitelist key[' . $key . ']');
                        }
                        else {
                            return false;
                        }
                    }
                }
                elseif ($tableElements->blacklist && in_array($key, $tableElements->blacklist, true)) {
                    if ($this->recordFailure) {
                        $failed = true;
                        $record[] = $this->recordCurrent('tableElements blacklists key[' . $key . ']');
                    }
                    else {
                        return false;
                    }
                }
                // Don't validate.
                continue;
            }

            if (!$this->internalChallenge(
                $value, $tableElements->rulesByElements[$key], $depth + 1, $keyPath . ' > ' . $key
            )) {
                if ($this->recordFailure) {
                    $failed = true;
                    // Don't record failure of child here.
                }
                else {
                    return false;
                }
            }

            $keys_found[] = $key;
        }

        // Find missing keys that aren't defined optional.
        $missing = array_diff($tableElements->keys, $keys_found);
        foreach ($missing as $key) {
            if (empty($tableElements->rulesByElements[$key]->optional)) {
                if ($this->recordFailure) {
                    $failed = true;
                    $record[] = $this->recordCurrent('tableElements missing required key[' . $key . ']');
                }
                else {
                    return false;
                }
            }
        }

        if ($failed && $this->recordFailure && $record) {
            $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
        }

        return !$failed;
    }

    /**
     * @todo
     *
     * @param object|array $subject
     * @param ListItems $listItems
     * @param int $depth
     * @param string $keyPath
     *
     * @return bool
     */
    protected function listItems($subject, ListItems $listItems, int $depth, string $keyPath) : bool
    {
        return false;
    }

    /**
     * @param string $ruleOrMessage
     * @param array|null $arguments
     *
     * @return string
     */
    protected function recordCurrent(string $ruleOrMessage, array $arguments = null) : string
    {
        if ($arguments === null) {
            return $ruleOrMessage;
        }
        $s = [];
        foreach ($arguments as $arg) {
            $arg_type = Helper::getType($arg);
            switch ($arg_type) {
                case 'boolean':
                    $s[] = $arg ? 'true' : 'false';
                    break;
                case 'integer':
                case 'float':
                    $s[] = '' . $arg;
                    break;
                case 'string':
                    $s[] = "'" . $arg . "'";
                    break;
                default:
                    $s[] = '(' . $arg_type . ')';
            }
        }
        return $ruleOrMessage . '(' . join(', ', $s) . ')';
    }

    /**
     * @param mixed $subject
     * @param int $depth
     * @param string $keyPath
     * @param string $message
     */
    protected function recordCumulative($subject, int $depth, string $keyPath, string $message) : void
    {
        $v = null;
        if (is_scalar($subject)) {
            $v = !is_string($subject) || strlen($subject) <= 50 ? $subject :
                (substr($subject, 50) . '...(truncated)');
        }
        $this->record[] = '(' . $depth . ') ' . $keyPath . ': ' . $message
            . ' - saw type[' . Helper::getType($subject) . ']'. ($v === null ? '' : (' value[' . $v . ']')) . '.';
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return get_object_vars($this);
    }
}
