<?php /** @noinspection PhpUnused */
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Interfaces\ChallengerInterface;

use SimpleComplex\Validate\Helper\Helper;
use SimpleComplex\Validate\RuleSet\ValidationRuleSet;
use SimpleComplex\Validate\RuleSet\TableElements;
use SimpleComplex\Validate\RuleSet\ListItems;

use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Exception\OutOfRangeException;

/**
 * Validator checking against a ruleset.
 * Supports recursive validation of object|array containers.
 *
 * @internal
 * Don't use this class directly, use challenge|challengeRecording() method.
 * @see AbstractRuleProvider::challenge()
 * @see AbstractRuleProvider::challengeRecording()
 *
 * @package SimpleComplex\Validate
 */
class ValidateAgainstRuleSet
{
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
    protected const RECURSION_LIMIT = 10;

    /**
     * Pseudo rules that the rules provider shan't (and mustn't) provide.
     *
     * IDE: may not be used, but that's fine.
     *
     * @var array
     */
    protected const NON_PROVIDER_RULES = [
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
    protected const RECORD_STRING_TRUNCATE = 40;

    /**
     * Failure record string sanitation needles.
     *
     * @var string[]
     */
    protected const RECORD_STRING_NEEDLES = [
        "\0", "\1", "\n", "\r", "\t", '"', "'",
    ];

    /**
     * Failure record string sanitation replacers.
     *
     * @var string[]
     */
    protected const RECORD_STRING_REPLACERS = [
        '_NUL_', '_SOH_', '_NL_', '_CR_', '_TB_', '”', '’',
    ];

    /**
     * The (most of the) methods of the Validate instance will be the rules
     * available.
     *
     * @var RuleProviderInterface
     */
    protected $ruleProvider;

    /**
     * @var bool
     */
    protected $recordFailure = false;

    /**
     * @var bool
     */
    protected $continueOnFailure = false;

    /**
     * @var array
     */
    protected $record = [];


//    /**
//     * Reference to first object instantiated via the getInstance() method,
//     * using a specific rule provider,
//     * no matter which parent/child class the method was/is called on.
//     *
//     * @var ValidateAgainstRuleSet[]
//     */
//    protected static $instanceByValidateClass = [];
//
//    /**
//     * First object instantiated via this method, using that rule provider,
//     * disregarding which ValidateAgainstRuleSet class called on.
//     *
//     * Does not allow constructor $options argument because that would affect
//     * instance state, voiding the warranty that the requested and referred
//     * returned instances are effectively identical.
//     *
//     * @see AbstractRuleProvider::challenge()
//     *
//     * @param RuleProviderInterface $ruleProvider
//     *
//     * @return ValidateAgainstRuleSet
//     *      static, really, but IDE might not resolve that.
//     *
//     * @throws BadMethodCallException
//     *      If passed more than one argument.
//     */
//    public static function getInstance(RuleProviderInterface $ruleProvider)
//    {
//        if (func_num_args() > 1) {
//            throw new BadMethodCallException(
//                'Method allows only one argument (a rule provider), passing options would void warranty'
//                . ' that requested and returned instance are effectvely identical, saw secondary argument type['
//                . Helper::getType(func_get_arg(1)) . '].'
//            );
//        }
//        $provider_class = get_class($ruleProvider);
//        return static::$instanceByValidateClass[$provider_class] ??
//            (static::$instanceByValidateClass[$provider_class] = new static($ruleProvider));
//    }

    /**
     * Use Validate::challenge() instead of this.
     *
     * @see AbstractRuleProvider::challenge()
     *
     * @param RuleProviderInterface $ruleProvider
     * @param int $options
     *      Bitmask, see ChallengerInterface bitmask flag constants.
     */
    public function __construct(RuleProviderInterface $ruleProvider, int $options = 0) {
        $this->ruleProvider = $ruleProvider;

        if ($options) {
            if (($options & ChallengerInterface::RECORD)) {
                $this->recordFailure = true;
                // Ignore unless recording.
                if (($options & ChallengerInterface::CONTINUE)) {
                    $this->continueOnFailure = true;
                }
            }
        }
    }

    /**
     * @see AbstractRuleProvider::challengeRecording()
     *
     * @return string[]
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * Use ValidateUnchecked::challenge() instead of this.
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
    public function challenge($subject, $ruleSet, string $keyPath = '@') : bool
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

        // Filter pseudo-rules from ordinary rules.-----------------------------
        $rule_methods = [];
        $nullable = $has_loopable = false;
        $alternativeEnum =
            $alternativeRuleSet =
            $tableElements = $listItems = null;
        foreach ($ruleSet as $ruleName => $argument) {
            switch ($ruleName) {
                case 'optional':
                    /**
                     * Do nothing, ignore here. Only used in tableElements.
                     * @see ValidateAgainstRuleSet::tableElements()
                     */
                    break;
                case 'nullable':
                case 'allowNull':
                    /**
                     * @see RuleProviderInterface::null()
                     */
                case 'null':
                    $nullable = true;
                    break;
                case 'alternativeEnum':
                    /** @var array $alternativeEnum */
                    $alternativeEnum = $argument;
                    break;
                case 'alternativeRuleSet':
                    /** @var ValidationRuleSet $alternativeRuleSet */
                    $alternativeRuleSet = $argument;
                    break;
                case 'tableElements':
                    /** @var TableElements $tableElements */
                    $tableElements = $argument;
                    break;
                case 'listItems':
                    /** @var ListItems $listItems */
                    $listItems = $argument;
                    break;
                default:
                    // Get type affiliation, and check that the rule exists.
                    $type = $this->ruleProvider->getTypeRuleType($ruleName) ??
                        $this->ruleProvider->getPatternRuleType($ruleName) ?? null;
                    // Doesn't check for renamed rule here.
                    // RuleSetGenerator does that.
                    // All rulesets have to be created anew, from source
                    // (JSON, PHP arrays), whenever this library is updated.
                    if (!$type) {
                        throw new InvalidRuleException(
                            'Unknown validation rule[' . $ruleName . '], at (' . $depth . ') ' . $keyPath . '.'
                        );
                    }
                    // tableElements|listItems require loopable check;
                    // if none found ad hoc check will be used.
                    if ($type == Type::LOOPABLE) {
                        $has_loopable = true;
                    }
                    $rule_methods[$ruleName] = $argument;
            }
        }

        // Roll it.
        $passed = true;
        $record = [];

        if ($subject === null) {
            if ($nullable) {
                return true;
            }
            // Continue to alternativeEnum|alternativeRuleSet (if any).
            $passed = false;
        }

        if ($passed) {
            // Do ordinary rules.-----------------------------------------------
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
            // Do alternativeEnum/alternativeRuleSet.---------------------------

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
            // Nested alternative fallback ruleset?
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
     * Pseudo rule listing ValidationRuleSets of elements of a 'loopable'
     * object|array subject.
     *
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

        // Iterate by order of subject buckets.---------------------------------
        foreach ($subject as $key => $value) {
            // PHP numeric index is not consistently integer.
            $sKey = '' . $key;
            if (!in_array($sKey, $table_keys, true)) {
                if ($tableElements->exclusive) {
                    // Subject object|array must only contain keys defined
                    // by rulesByElements.
                    if ($this->recordFailure) {
                        $record[] = $this->recordCurrent(
                            'tableElements excludes key[' . $key . ']'
                        );
                        if (!$this->continueOnFailure) {
                            $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                            return false;
                        }
                        $passed = false;
                    }
                    else {
                        return false;
                    }
                }
                elseif ($tableElements->whitelist) {
                    // Subject object|array must _only_ contain these keys,
                    // apart from the keys defined by rulesByElements.
                    if (!in_array($sKey, $tableElements->whitelist, true)) {
                        if ($this->recordFailure) {
                            $record[] = $this->recordCurrent('tableElements doesn\'t whitelist key[' . $key . ']');
                            if (!$this->continueOnFailure) {
                                $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                                return false;
                            }
                            $passed = false;
                        }
                        else {
                            return false;
                        }
                    }
                }
                elseif ($tableElements->blacklist && in_array($sKey, $tableElements->blacklist, true)) {
                    // Subject array|object must _not_ contain these keys,
                    // apart from the keys defined by rulesByElements.
                    if ($this->recordFailure) {
                        $record[] = $this->recordCurrent('tableElements blacklists key[' . $key . ']');
                        if (!$this->continueOnFailure) {
                            $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                            return false;
                        }
                        $passed = false;
                    }
                    else {
                        return false;
                    }
                }
                // Don't validate.
                continue;
            }

            // Check subject bucket against same-keyed ruleset.
            if (!$this->internalChallenge(
                $value, $tableElements->rulesByElements[$sKey], $depth + 1, $keyPath . ' > ' . $key
            )) {
                if ($this->recordFailure) {
                    // Don't record failure of child here.
                    if (!$this->continueOnFailure) {
                        return false;
                    }
                    $passed = false;
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
                    $record[] = $this->recordCurrent('tableElements missing required key[' . $key . ']');
                    if (!$this->continueOnFailure) {
                        $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                        return false;
                    }
                    $passed = false;
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
     * Pseudo rule using a common ruleset on every element of object|array
     * subject.
     *
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
                    $record[] = $this->recordCurrent(
                        'listItems max length ' . $maxOccur . ' exceeded at key[' . $key . ']'
                    );
                    if (!$this->continueOnFailure) {
                        $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                        return false;
                    }
                    $passed = false;
                }
                else {
                    return false;
                }
                break;
            }

            // Check subject bucket against the common ruleset.
            if (!$this->internalChallenge(
                $value, $listItems->itemRules, $depth + 1, $keyPath . ' > ' . $key
            )) {
                if ($this->recordFailure) {
                    // Don't record failure of child here.
                    if (!$this->continueOnFailure) {
                        return false;
                    }
                    $passed = false;
                }
                else {
                    return false;
                }
            }
        }

        if ($listItems->minOccur && $length < $listItems->minOccur) {
            if ($this->recordFailure) {
                $record[] = $this->recordCurrent(
                    'listItems min length ' . $listItems->minOccur . ' not satisfied'
                );
                if (!$this->continueOnFailure) {
                    $this->recordCumulative($subject, $depth, $keyPath, reset($record));
                    return false;
                }
                $passed = false;
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
     * Formats failure message of a single failure.
     *
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
