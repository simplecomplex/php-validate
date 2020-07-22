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
 * @see AbstractRuleProvider::challenge()
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
     * @see AbstractRuleProvider::challenge()
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
        'nullable',
        'alternativeEnum',
        'alternativeRuleSet',
        'tableElements',
        'listItems',
    ];

    /**
     * Failure record string (multibyte) truncation.
     *
     * @var int
     */
    const RECORD_STRING_TRUNCATE = 40;

    /**
     * Failure record string sanitation needles.
     *
     * @var string[]
     */
    const RECORD_STRING_NEEDLES = [
        "\0", "\1", "\n", "\r", "\t", '"', "'",
    ];

    /**
     * Failure record string sanitation replacers.
     *
     * @var string[]
     */
    const RECORD_STRING_REPLACERS = [
        '_NUL_', '_SOH_', '_NL_', '_CR_', '_TB_', '”', '’',
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
     * @see AbstractRuleProvider::challenge()
     * @see AbstractRuleProvider::challengeRecording()
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
     * @see AbstractRuleProvider::challenge()
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
     * @see AbstractRuleProvider::challenge()
     * @see AbstractRuleProvider::challengeRecording()
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
    public function challenge($subject, $ruleSet, string $keyPath = 'root') : bool
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
            // Not great performance-wise, but referring RuleSetFactory
            // would lock RuleSetFactory and rule-provider together too tightly.
            (new RuleSetFactory\RuleSetFactory($this->ruleProvider))
                ->make($ruleSet, 0, $keyPath),
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
    protected function internalChallenge($subject, ValidationRuleSet $ruleSet, $depth, $keyPath) : bool
    {
        if ($depth >= static::RECURSION_LIMIT) {
            throw new OutOfRangeException(
                'Stopped recursive validation by rule-set at limit['
                . static::RECURSION_LIMIT . '], at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $rule_methods = [];
        $nullable = $has_loopable = false;
        $alternativeEnum =
            $alternativeRuleSet =
            $tableElements = $listItems = null;
        foreach ($ruleSet as $rule => $argument) {
            switch ($rule) {
                case 'optional':
                    /**
                     * Do nothing, ignore here. Only used in tableElements.
                     * @see ValidateAgainstRuleSet::tableElements()
                     */
                    break;
                case 'nullable':
                case 'allowNull':
                    /**
                     * There must be a null method.
                     * @see RuleProviderInterface::null()
                     */
                case 'null':
                    $nullable = true;
                    break;
                case 'enum':
                    /** @var array $enum */
                    $enum = reset($argument);
                    break;
                case 'alternativeEnum':
                    /** @var array $alternativeEnum */
                    $alternativeEnum = $argument;
                    break;
                case 'alternativeRuleSet':
                    /** @var ValidationRuleSet $alternativeRuleSet */
                    $alternativeRuleSet = $argument;
                    break;
                case 'array':
                case 'indexedArray':
                case 'keyedArray':
                case 'loopable':
                    $has_loopable = true;
                    break;
                case 'tableElements':
                    $tableElements = $argument;
                    break;
                case 'listItems':
                    // No need to check for type; ValidationRuleSet do that,
                    // and makes it object.
                    $listItems = $argument;
                    break;
                default:
                    if (!in_array($rule, $this->ruleMethods)) {
                        throw new InvalidRuleException(
                            'Unknown validation rule[' . $rule . '], at (' . $depth . ') ' . $keyPath . '.'
                        );
                    }
                    $rule_methods[$rule] = $argument;
            }
        }

        // Roll it.
        $passed = true;
        $record = [];

        if ($subject === null) {
            if ($nullable) {
                return true;
            }
            // Continue to alternativeEnum|alternativeRuleSet check.
            $passed = false;
        }

        if ($passed) {
            foreach ($rule_methods as $method => $args) {
                // We expect more boolean trues than arrays;
                // few Validate methods take secondary args.
                if ($args === true) {
                    if (!$this->ruleProvider->{$method}($subject)) {
                        $passed = false;
                        if ($this->recordFailure) {
                            $record[] = $this->recordCurrent($method);
                        }
                        break;
                    }
                }
                /**
                 * RuleSetGenerator also checks for falsy nor non-array $args,
                 * but we play safe in case the ruleset has been tampered with.
                 * @see RuleSetFactory\RuleSetGenerator::resolveCandidates()
                 */
                elseif (is_array($args)) {
                    if (!$this->ruleProvider->{$method}($subject, ...$args)) {
                        $passed = false;
                        if ($this->recordFailure) {
                            $record[] = $this->recordCurrent($method, $args);
                        }
                        break;
                    }
                }
                else {
                    throw new InvalidRuleException(
                        'Validation ruleset rule[' . $method . '] argument type[' . Helper::getType($args) . ']'
                        . ' is not true|array' . ', at (' . $depth . ') ' . $keyPath . '.'
                    );
                }
            }
        }

        if (!$passed) {
            // Matches one of a list of alternative (scalar|null) values?
            if ($alternativeEnum) {
                if ($this->ruleProvider->enum($subject, $alternativeEnum)) {
                    // scalar|null; tableElements|listItems irrelevant.
                    return true;
                }
                if ($this->recordFailure) {
                    $record[] = $this->recordCurrent('alternativeEnum', $alternativeEnum);
                }
            }
            if ($alternativeRuleSet) {
                $passed = $this->internalChallenge($subject, $alternativeRuleSet, $depth, $keyPath);
            }
            if (!$passed) {
                if ($this->recordFailure) {
                    $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
                }
                return false;
            }
        }

        // Didn't fail.
        if (!$tableElements && !$listItems) {
            return true;
        }

        // tableElements|listItems require loopable container.
        if (!$has_loopable && !$this->ruleProvider->loopable($subject)) {
            if ($this->recordFailure) {
                $this->recordCumulative(
                    $subject, $depth, $keyPath, ($tableElements ? 'tableElements' : 'listItems') . '.loopable'
                );
            }
            return false;
        }

        // If tableElements pass then listItems will be ignored.
        if ($tableElements) {
            $passed = $this->tableElements($subject, $tableElements, $depth, $keyPath);
            if ($passed) {
                return true;
            }
            if (!$listItems) {
                return false;
            }
        }

        $passed = $this->listItems($subject, $listItems, $depth, $keyPath);
        return $passed;
    }

    /**
     * @return array
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * Iterates by order of subject buckets.
     *
     * Subject must be loopable, and that must be checked prior to call.
     * @see TypeRulesTrait::loopable()
     * @see ValidateAgainstRuleSet::internalChallenge()
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
        // PHP numeric index is not consistently integer.
        $table_keys = $tableElements->keys;

        $passed = true;
        $record = [];
        $keys_found = [];
        foreach ($subject as $key => $value) {
            // PHP numeric index is not consistently integer.
            $sKey = '' . $key;
            if (!in_array($sKey, $table_keys, true)) {
                if ($tableElements->exclusive) {
                    if ($this->recordFailure) {
                        $passed = false;
                        $record[] = $this->recordCurrent(
                            'tableElements excludes key[' . $key . ']'
                        );
                    }
                    else {
                        return false;
                    }
                }
                elseif ($tableElements->whitelist) {
                    if (!in_array($sKey, $tableElements->whitelist, true)) {
                        if ($this->recordFailure) {
                            $passed = false;
                            $record[] = $this->recordCurrent('tableElements doesn\'t whitelist key[' . $key . ']');
                        }
                        else {
                            return false;
                        }
                    }
                }
                elseif ($tableElements->blacklist && in_array($sKey, $tableElements->blacklist, true)) {
                    if ($this->recordFailure) {
                        $passed = false;
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
                $value, $tableElements->rulesByElements[$sKey], $depth + 1, $keyPath . ' > ' . $key
            )) {
                if ($this->recordFailure) {
                    $passed = false;
                    // Don't record failure of child here.
                }
                else {
                    return false;
                }
            }

            $keys_found[] = $sKey;
        }

        // Find missing keys that aren't defined optional.
        $missing = array_diff($table_keys, $keys_found);
        foreach ($missing as $key) {
            // PHP numeric index is not consistently integer.
            $sKey = '' . $key;
            if (empty($tableElements->rulesByElements[$sKey]->optional)) {
                if ($this->recordFailure) {
                    $passed = false;
                    $record[] = $this->recordCurrent('tableElements missing required key[' . $key . ']');
                }
                else {
                    return false;
                }
            }
        }

        if (!$passed && $this->recordFailure && $record) {
            $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
        }

        return $passed;
    }

    /**
     * Subject must be loopable, and that must be checked prior to call.
     * @see TypeRulesTrait::loopable()
     * @see ValidateAgainstRuleSet::internalChallenge()
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
        $passed = true;
        $record = [];

        $length = 0;
        $maxOccur = $listItems->maxOccur;
        foreach ($subject as $key => $value) {
            ++$length;
            if ($maxOccur && $length > $maxOccur) {
                if ($this->recordFailure) {
                    $passed = false;
                    $record[] = $this->recordCurrent(
                        'listItems max length ' . $maxOccur . ' exceeded at key[' . $key . ']'
                    );
                }
                else {
                    return false;
                }
                break;
            }

            if (!$this->internalChallenge(
                $value, $listItems->itemRules, $depth + 1, $keyPath . ' > ' . $key
            )) {
                if ($this->recordFailure) {
                    $passed = false;
                    // Don't record failure of child here.
                }
                else {
                    return false;
                }
            }
        }

        if ($listItems->minOccur && $length < $listItems->minOccur) {
            if ($this->recordFailure) {
                $passed = false;
                $record[] = $this->recordCurrent(
                    'listItems min length ' . $listItems->minOccur . ' not satisfied'
                );
            }
            else {
                return false;
            }
        }

        if (!$passed && $this->recordFailure && $record) {
            $this->recordCumulative($subject, $depth, $keyPath, join('|', $record));
        }

        return $passed;
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
     * Saves failure message of a ruleset.
     *
     * Includes subject's value if scalar; truncated and sanitized if string.
     *
     * @param mixed $subject
     * @param int $depth
     * @param string $keyPath
     * @param string $message
     */
    protected function recordCumulative($subject, int $depth, string $keyPath, string $message) : void
    {
        $value = null;
        $type = '(' . Helper::getType($subject);
        if ($subject !== null) {
            if (is_scalar($subject)) {
                $value = $subject;
                if (is_bool($subject)) {
                    $value = $subject ? 'true' : 'false';
                }
                elseif (is_string($subject)) {
                    // Truncate and sanitize value.
                    $unicode_length = mb_strlen($subject);
                    $type .= ':' . $unicode_length . ':' . strlen($subject);
                    if ($unicode_length > static::RECORD_STRING_TRUNCATE) {
                        $type .= ':' . static::RECORD_STRING_TRUNCATE;
                        $value = mb_substr($value, 0, static::RECORD_STRING_TRUNCATE);
                    }
                    $value = '`'
                        . addcslashes(
                            str_replace(static::RECORD_STRING_NEEDLES, static::RECORD_STRING_REPLACERS, $value),
                            "\0..\37"
                        )
                        . '`';
                }
            }
            elseif (is_array($subject) || $subject instanceof \Countable) {
                $type .= ':' . count($subject);
            }
        }
        $type .= ')';
        $this->record[] = '(' . $depth . ') ' . $keyPath . ': ' . $message
            . ' - saw ' . $type . ($value === null ? '' : (' ' . $value)) . '.';
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return get_object_vars($this);
    }
}
