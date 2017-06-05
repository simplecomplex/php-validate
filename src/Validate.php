<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use Psr\Log\LoggerInterface;
use SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait;
use SimpleComplex\Utils\Unicode;
use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\BadMethodCallException;

/**
 * Validate almost anything.
 *
 *
 * Some string methods return true on empty
 * ----------------------------------------
 * Combine with the 'nonEmpty' rule if requiring non-empty.
 * They are:
 * - unicode, unicodePrintable, unicodeMultiLine
 * - ascii, asciiPrintable, asciiMultiLine
 * - plainText
 *
 * Some methods return string on pass
 * ----------------------------------
 * Composite type checkers like:
 * - numeric, collection, hashTable
 *
 * Maximum number of rule method parameters
 * ----------------------------------------
 * A rule method is not allowed to have more than 5 parameters,
 * that is: 1 for the var to validate and max. 4 secondary
 * (specifying) parameters.
 * ValidateByRules::challenge() will err when given more than 4 secondary args.
 *
 * Rule methods invalid arg checks
 * -------------------------------
 * Rule methods that take more arguments than the $var to validate:
 * - must check those arguments for type (and emptyness, if required)
 * - must log meaningful error message (if logger) and return false, or throw exception
 * Reasons:
 * - parameter type declarations don't support someType|otherType&not-empty
 * - it should be possible to fail gracefully; a non-passed validation should stop the party
 *   in a well constructed application anyway
 *
 * Intended as singleton - ::getInstance() - but constructor not protected.
 *
 * @package SimpleComplex\Validate
 */
class Validate implements RuleProviderInterface
{
    /**
     * @see \SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait
     *
     * First object instantiated via this method, disregarding class called on.
     * @public
     * @static
     * @see \SimpleComplex\Utils\Traits\GetInstanceOfFamilyTrait::getInstance()
     */
    use GetInstanceOfFamilyTrait;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'validate';

    /**
     * Methods of this class that a ValidateByRules instance should never call.
     *
     * Does not contain __construct nor __call; would be slightly paranoid.
     *
     * @var array
     */
    const NON_RULE_METHODS = [
        'getInstance',
        'flushInstance',
        '__construct',
        'make',
        'getLogger',
        'getNonRuleMethods',
        '__call',
        'challengeRules',
        'challengeRulesRecording',
    ];

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var Unicode
     */
    protected $unicode;

    /**
     * @var array
     */
    protected $nonRuleMethods = [];

    /**
     * Do always throw exception on logical/runtime error, even when logger
     * available (default not).
     *
     * @var bool
     */
    protected $errUnconditionally;

    /**
     * When provided with a logger, rule methods will fail gracefully when
     * given wrong argument(s) - otherwise they throw exception. Except if
     * truthy option errUnconditionally.
     *
     * @see Validate::getInstance()
     * @see Validate::setLogger()
     *
     * @param LoggerInterface|null
     *      PSR-3 logger, if any.
     * @param array $options {
     *      @var bool errUnconditionally Default: false.
     * }
     */
    public function __construct(
        /*?LoggerInterface*/ $logger = null,
        array $options = [
            'errUnconditionally' => false,
        ]
    ) {
        // Dependencies.--------------------------------------------------------
        // Logger is not required.
        $this->logger = $logger;

        // Extending class' constructor might provide instance by other means.
        if (!$this->unicode) {
            $this->unicode = Unicode::getInstance();
        }

        // Business.------------------------------------------------------------
        $this->errUnconditionally = !empty($options['errUnconditionally']);

        $this->nonRuleMethods = self::NON_RULE_METHODS;
        /* // Extending class must merge non-rule-methods class constants.
         * $parent_class = get_parent_class();
         * if (defined($parent_class . '::NON_RULE_METHODS')) {
         *   $this->nonRuleMethods = array_merge(
         *     $this->nonRuleMethods,
         *     constant($parent_class . '::NON_RULE_METHODS')
         *   );
         * }
         */
    }

    /**
     * Overcome mutual dependency, provide a logger after instantiation.
     *
     * This class does not need a logger at all. But errors are slightly more
     * debuggable provided a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger) /*: void*/
    {
        $this->logger = $logger;
    }

    /**
     * Makes our logger available for a ValidateByRules instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger() /*: ?LoggerInterface*/
    {
        return $this->logger;
    }

    /**
     * @return array
     */
    public function getNonRuleMethods() : array
    {
        if (!$this->nonRuleMethods) {
            $this->nonRuleMethods = self::NON_RULE_METHODS;
        }
        return $this->nonRuleMethods;
    }

    /**
     * Log (if logger) call to non-existent rule method.
     *
     * By design, ValidateByRules::challenge() should not be able to call a non-existent method
     * of this class.
     * But external call to Validate::noSuchRule() is somewhat expectable.
     *
     * @see ValidateByRules::challenge()
     *
     * @throws BadMethodCallException
     *
     * @param string $name
     * @param array $arguments
     */
    public function __call($name, $arguments)
    {
        if ($this->logger) {
            // Log warning instaed of error, because we also throw exception.
            $this->logger->warning(
                'Class ' . get_class($this) . ', and parents, provide no rule method \'{rule_method}\'.',
                [
                    'type' => static::LOG_TYPE,
                    'rule_method' => $name,
                ]
            );
        }
        throw new BadMethodCallException('Undefined validation rule[' . $name . '].');
    }


    // Validate by list of rules.---------------------------------------------------------------------------------------

    /**
     * Validate by a list of rules.
     *
     * Reuses the same ValidateByRules instance on every call.
     * Instance saved on ValidateByRules class, not here.
     *
     * @code
     * // A little helper class.
     * class Bicycle
     * {
     *     public $wheels = 0;
     *     public $saddles;
     *     public $sound = '';
     *     public $accessories = [];
     *     public function __construct($wheels, $saddles, $sound, $accessories)
     *     {
     *         $this->wheels = $wheels;
     *         $this->saddles = $saddles;
     *         $this->sound = $sound;
     *         $this->accessories = $accessories;
     *     }
     * }
     * // Declare validation rules for a Bicycle of your taste.
     * $rules = [
     *     // (str) rule => (arr) arguments
     *     'class' => [
     *         'Bicycle'
     *     ],
     *     // A bike has properties.
     *     '_elements_' => [
     *         'wheels' => [
     *             // You don't have to give an arguments array, when no arguments.
     *             'integer',
     *             // Zero makes no bike, 4 makes a waggon.
     *             'range' => [
     *                 1,
     *                 3
     *             ]
     *         ],
     *         'saddles' => [
     *             'integer',
     *             // We prefer number of saddles, but any will also do.
     *             'alternativeEnum' => [
     *                 true,
     *             ]
     *         ],
     *         'sound' => [
     *             // When no arguments, true will do just a well as no array and empty array.
     *             'string' => true,
     *             // Beware that actual argument(s) must always be array.
     *             'enum' => [
     *                 // First, and only, argument.
     *                 [
     *                     'silent',
     *                     'swooshy',
     *                     'clattering',
     *                 ]
     *             ]
     *         ],
     *         'accessories' => [
     *             'array',
     *             '_elements_' => [
     *                 'luggageCarrier' => [
     *                     'boolean',
     *                     // We don't really care if there's a luggage carrier or not.
     *                     'optional'
     *                 ]
     *             ]
     *         ],
     *     ]
     * ];
     * // Create a bike.
     * $bike = new Bicycle(
     *     2,
     *     true,
     *     'swooshy',
     *     [
     *         'luggageCarrier' => false,
     *     ]
     * );
     * // Validate it.
     * $good_bike = Validate::make()->challengeRules($bike, $rules);
     * @endcode
     *
     * @param mixed $var
     * @param array $rules
     *
     * @return bool
     */
    public function challengeRules($var, array $rules)
    {
        // Extending class do not have to override this method;
        // the class name used as name arg will be the sub class' name.
        return ValidateByRules::getInstance(
            $this,
            [
                'errUnconditionally' => $this->errUnconditionally,
            ]
        )->challenge($var, $rules);
    }

    /**
     * Validate by a list of rules,
     *
     * Creates a new ValidateByRules instance on every call.
     *
     * @code
     * $good_bike = Validate::make()->challengeRulesRecording($bike, $rules);
     * if (empty($good_bike['passed'])) {
     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
     * }
     * @endcode
     *
     * @param mixed $var
     * @param array $rules
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     */
    public function challengeRulesRecording($var, array $rules)
    {
        $validateByRules = new ValidateByRules($this, [
            'errUnconditionally' => $this->errUnconditionally,
            'recordFailure' => true,
        ]);

        $validateByRules->challenge($var, $rules);
        $record = $validateByRules->getRecord();

        return [
            'passed' => !$record,
            'record' => $record,
        ];
    }


    // Rules.-----------------------------------------------------------------------------------------------------------

    // Type indifferent.--------------------------------------------------------

    /**
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function empty($var) : bool
    {
        if (!$var) {
            // Stringed zero - '0' - is not empty.
            return $var !== '0';
        }
        if (is_object($var) && !get_object_vars($var)) {
            return true;
        }
        return false;
    }

    /**
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonEmpty($var) : bool
    {
        return !$this->empty($var);
    }

    /**
     * Compares type strict, and allowed values must be scalar or null.
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     * @param array $allowedValues
     *      [
     *          0: some scalar
     *          1: null
     *          3: other scalar
     *      ]
     *
     * @return bool
     */
    public function enum($var, $allowedValues) : bool
    {
        if (!$allowedValues || !is_array($allowedValues)) {
            $msg = 'allowedValues is not non-empty array.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'allowedValues' => $allowedValues,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        $i = -1;
        foreach ($allowedValues as $allowed) {
            ++$i;
            if ($allowed !== null && !is_scalar($allowed)) {
                $msg = 'allowedValues bucket ' . $i . ' is not scalar or null.';
                if ($this->logger) {
                    $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                        'type' => static::LOG_TYPE,
                        'variables' => [
                            'allowedValues' => $allowedValues,
                        ],
                    ]);
                    if (!$this->errUnconditionally) {
                        return false;
                    }
                }
                throw new InvalidArgumentException('Arg ' . $msg);
            }

            if ($var === $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $pattern
     *      /regular expression/
     *
     * @return bool
     */
    public function regex($var, $pattern) : bool
    {
        if (!$pattern || !is_string($pattern)) {
            $msg = 'pattern is not non-empty string.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'pattern' => $pattern,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return !!preg_match($pattern, '' . $var);
    }

    // Type checkers.-----------------------------------------------------------

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function boolean($var) : bool
    {
        return is_bool($var);
    }

    /**
     * Integer or float.
     *
     * @see Validate::numeric()
     *
     * @see Validate::bit32()
     * @see Validate::bit64()
     * @see Validate::positive()
     * @see Validate::negative()
     * @see Validate::nonNegative()
     * @see Validate::min()
     * @see Validate::max()
     * @see Validate::range()
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (integer|float) on pass, boolean false on failure.
     */
    public function number($var)
    {
        if (is_int($var)) {
            return 'integer';
        }
        if (is_float($var)) {
            return 'float';
        }
        return false;
    }

    /**
     * @see Validate::digit()
     *
     * @see Validate::bit32()
     * @see Validate::bit64()
     * @see Validate::positive()
     * @see Validate::negative()
     * @see Validate::nonNegative()
     * @see Validate::min()
     * @see Validate::max()
     * @see Validate::range()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function integer($var) : bool
    {
        return is_int($var);
    }

    /**
     * @see Validate::bit32()
     * @see Validate::bit64()
     * @see Validate::positive()
     * @see Validate::negative()
     * @see Validate::nonNegative()
     * @see Validate::min()
     * @see Validate::max()
     * @see Validate::range()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function float($var) : bool
    {
        return is_float($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function string($var) : bool
    {
        return is_string($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function null($var) : bool
    {
        return $var === null;
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function resource($var) : bool
    {
        return is_resource($var);
    }

    /**
     * Object or array.
     *
     * Superset of all other object and array type(ish) checkers; here:
     * - hashTable, object, class, array, numArray, assocArray
     *
     * Not related to PHP>=7 \DS\Collection (Traversable, Countable, JsonSerializable).
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|object) on pass, boolean false on failure.
     */
    public function collection($var)
    {
        if (is_array($var)) {
            return 'array';
        }
        return ($var && is_object($var)) ? 'object' : false;
    }

    /**
     * Object or empty array or associative array.
     *
     * @param $var
     *
     * @return string|bool
     *      String (array|object) on pass, boolean false on failure.
     */
    public function hashTable($var)
    {
        if (is_array($var)) {
            if (!$var || !ctype_digit(join('', array_keys($var)))) {
                return 'array';
            }
            return false;
        }
        return is_object($var) ? 'object' : false;
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function object($var) : bool
    {
        return $var && is_object($var);
    }

    /**
     * Is object and is of that class or has it as ancestor.
     *
     * @uses is_a()
     *
     * @param mixed $var
     * @param string $className
     *
     * @return bool
     */
    public function class($var, $className) : bool
    {
        if (!$className || !is_string($className)) {
            $msg = 'className is not a non-empty string.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'className' => $className,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return $var && is_object($var) && is_a($var, $className);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function array($var) : bool
    {
        return is_array($var);
    }

    /**
     * Empty array or numerically indexed array.
     *
     * Does not check if the array's index is complete and correctly sequenced.
     *
     * @param mixed $var
     *
     * @return bool
     *      True: empty array, or all keys are integers.
     */
    public function numArray($var) : bool
    {
        if (!is_array($var)) {
            return false;
        }
        if (!$var) {
            return true;
        }
        return ctype_digit(join('', array_keys($var)));
    }

    /**
     * Empty array or keyed array.
     *
     * @param mixed $var
     *
     * @return bool
     *      True: empty array, or at least one key is not integer.
     */
    public function assocArray($var) : bool
    {
        if (!is_array($var)) {
            return false;
        }
        if (!$var) {
            return true;
        }
        return !ctype_digit(join('', array_keys($var)));
    }


    // Numbers or stringed numbers.---------------------------------------------

    /**
     * Integer, float or stringed integer/float (but not e-notation).
     *
     * @code
     * $numeric = $validate->numeric($weakly_typed_input);
     * switch('' . $numeric) {
     *   case 'integer':
     *     work_with_integer((int) $weakly_typed_input);
     *     break;
     *   case 'float':
     *     work_with_float((float) $weakly_typed_input);
     *     break;
     *   default:
     *     return go_away();
     * }
     * if ($numeric
     * @endcode
     *
     * @see Validate::number()
     *
     * @see Validate::bit32()
     * @see Validate::bit64()
     * @see Validate::positive()
     * @see Validate::negative()
     * @see Validate::nonNegative()
     * @see Validate::min()
     * @see Validate::max()
     * @see Validate::range()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return string|bool
     *      String (integer|float) on pass, boolean false on failure.
     */
    public function numeric($var)
    {
        $v = '' . $var;
        $float = false;
        if (strpos($v, '.') !== false) {
            $count = 0;
            $v = str_replace('.', '', $v, $count);
            if ($count != 1) {
                return false;
            }
            $float = true;
        }
        // Yes, ctype_... returns false on ''.
        if (ctype_digit($v)) {
            return $float ? 'float' : 'integer';
        }
        return false;
    }

    /**
     * Non-negative integer or stringed integer.
     *
     * If negative integer should pass, use numeric() and then check it's return value.
     *
     * @see Validate::integer()
     *
     * @see Validate::bit32()
     * @see Validate::bit64()
     * @see Validate::positive()
     * @see Validate::negative()
     * @see Validate::nonNegative()
     * @see Validate::min()
     * @see Validate::max()
     * @see Validate::range()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function digital($var) : bool
    {
        // Yes, ctype_... returns fals on ''.
        return ctype_digit('' . $var);
    }

    /**
     * @param $mixed $var
     * @param string $decimalMarker
     * @param string $thousandSep
     * @return bool
     *
    public function decimal($var, $decimalMarker, $thousandSep = '')
    {
        // Should integer|'integer' count as decimal?
        return false;
    }*/

    /**
     * Hexadeximal number (string).
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function hex($var, string $case = '') : bool
    {
        switch ($case) {
            case 'lower':
                $v = '' . $var;
                return $v === '' ? false : !!preg_match('/^[a-f\d]+$/', '' . $v);
            case 'upper':
                $v = '' . $var;
                return $v === '' ? false : !!preg_match('/^[A-F\d]+$/', '' . $v);
        }
        // Yes, ctype_... returns false on ''.
        return !!ctype_xdigit('' . $var);
    }


    // Numeric secondaries.-----------------------------------------------------

    /**
     * 32-bit integer.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function bit32($var) : bool
    {
        return $var >= -2147483648 && $var <= 2147483647;
    }

    /**
     * 64-bit integer.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function bit64($var) : bool
    {
        return $var >= -9223372036854775808 && $var <= 9223372036854775807;
    }

    /**
     * Positive number; not zero and not negative.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function positive($var) : bool
    {
        return $var > 0;
    }

    /**
     * Zero or positive number.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonNegative($var) : bool
    {
        return $var >= 0;
    }

    /**
     * Negative number; not zero and not positive.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function negative($var) : bool
    {
        return $var < 0;
    }

    /**
     * Numeric minimum.
     *
     * May produce false negative if args var and min both are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     * @param int|float $min
     *      Stringed number is not accepted.
     *
     * @return bool
     */
    public function min($var, $min) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            $msg = 'min is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return $var >= $min;
    }

    /**
     * Numeric maximum.
     *
     * May produce false negative if args var and max both are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @return bool
     */
    public function max($var, $max) : bool
    {
        if (!is_int($max) && !is_float($max)) {
            $msg = 'max is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return $var <= $max;
    }

    /**
     * Numeric range.
     *
     * May produce false negative if (at least) two of the args are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     * @param int|float $min
     *      Stringed number is not accepted.
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @return bool
     */
    public function range($var, $min, $max) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            $msg = 'min is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }
        if (!is_int($max) && !is_float($max)) {
            $msg = 'max is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }
        if ($max < $min) {
            $msg = 'max cannot be less than arg min.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return $var >= $min && $var <= $max;
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * Valid UTF-8.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function unicode($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true :
            // The PHP regex u modifier forces the whole subject to be evaluated
            // as UTF-8. And if any byte sequence isn't valid UTF-8 preg_match()
            // will return zero for no-match.
            // The s modifier makes dot match newline; without it a string consisting
            // of a newline solely would result in a false negative.
            !!preg_match('/./us', $v);
    }

    /**
     * Allows anything but lower ASCII and DEL.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *
     * @return bool
     *      True on empty.
     */
    public function unicodePrintable($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true :
            (
                !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
                // Prefix space to avoid expensive type check (boolean false).
                && !strpos(' ' . $v, chr(127))
            );
    }

    /**
     * Unicode printable that allows newline and (default) carriage return.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function unicodeMultiLine($var, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->unicodePrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $var
            )
        );
    }

    /**
     * String minimum multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeMinLength($var, int $min) : bool
    {
        if ($min < 0) {
            $msg = 'min is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        $v = '' . $var;
        if ($v === '') {
            return $min == 0;
        }
        return $this->unicode->strlen($v) >= $min;
    }

    /**
     * String maximum multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeMaxLength($var, int $max) : bool
    {
        if ($max < 0) {
            $msg = 'max is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        $v = '' . $var;
        if ($v === '') {
            // Unlikely, but correct.
            return $max == 0;
        }
        return $this->unicode->strlen($v) <= $max;
    }

    /**
     * String exact multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeExactLength($var, int $exact) : bool
    {
        if ($exact < 0) {
            $msg = 'exact is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'exact' => $exact,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        $v = '' . $var;
        if ($v === '') {
            return $exact == 0;
        }
        return $this->unicode->strlen($v) == $exact;
    }


    // ASCII string secondaries.------------------------------------------------

    /**
     * Full ASCII; 0-127.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function ascii($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : !!preg_match('/^[[:ascii:]]+$/', $v);
    }

    /**
     * Allows ASCII except lower ASCII and DEL.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function asciiPrintable($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : (
            !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH))
            // Prefix space to avoid expensive type check (boolean false).
            && !strpos(' ' . $v, chr(127))
        );
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function asciiMultiLine($var, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->asciiPrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $var
            )
        );
    }

    /**
     * String minimum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function minLength($var, int $min) : bool
    {
        if ($min < 0) {
            $msg = 'min is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'min' => $min,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return strlen('' . $var) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function maxLength($var, int $max) : bool
    {
        if ($max < 0) {
            $msg = 'max is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'max' => $max,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return strlen('' . $var) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws InvalidArgumentException
     *      Unless logger + falsy option errUnconditionally.
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     */
    public function exactLength($var, int $exact) : bool
    {
        if ($exact < 0) {
            $msg = 'exact is not non-negative integer.';
            if ($this->logger) {
                $this->logger->error(get_class($this) . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'exact' => $exact,
                    ],
                ]);
                if (!$this->errUnconditionally) {
                    return false;
                }
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        return strlen('' . $var) == $exact;
    }


    // ASCII specials.----------------------------------------------------------

    /**
     * ASCII alphanumeric.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function alphaNum($var, string $case = '') : bool
    {
        $v = '' . $var;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z\d]+$/';
                break;
            case 'upper':
                $regex = '/^[A-Z\d]+$/';
                break;
            default:
                $regex = '/^[a-zA-Z\d]+$/';
                break;
        }
        // ctype_... is no good for ASCII-only check, if PHP and server locale
        // is set to something non-English.
        return !!preg_match($regex, $v);
    }

    /**
     * Snake cased name: must start with alpha or underscore,
     * followed by alphanum/underscore.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function name($var, string $case = '') : bool
    {
        $v = '' . $var;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z_][a-z\d_]*$/';
                break;
            case 'upper':
                $regex = '/^[A-Z_][A-Z\d_]*$/';
                break;
            default:
                $regex = '/^[a-zA-Z_][a-zA-Z\d_]*$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * Snake cased name: must start with alpha, followed by alphanum/dash.
     *
     * NB: Cannot start with dash.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function dashName($var, string $case = '') : bool
    {
        $v = '' . $var;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z][A-Z\d\-]*$/';
                break;
            case 'upper':
                $regex = '/^[A-Z][A-Z\d\-]*$/';
                break;
            default:
                $regex = '/^[a-zA-Z][a-zA-Z\d\-]*$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function uuid($var, string $case = '') : bool
    {
        $v = '' . $var;
        if (strlen($v) != 36) {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[\da-f]{8}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{12}$/';
                break;
            case 'upper':
                $regex = '/^[\dA-F]{8}\-[\dA-F]{4}\-[\dA-F]{4}\-[\dA-F]{4}\-[\dA-F]{12}$/';
                break;
            default:
                $regex = '/^[\da-fA-F]{8}\-[\da-fA-F]{4}\-[\da-fA-F]{4}\-[\da-fA-F]{4}\-[\da-fA-F]{12}$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      False on empty.
     */
    public function base64($var) : bool
    {
        return !!preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $var);
    }

    /**
     * Iso-8601 datetime timestamp, which doesn't require seconds,
     * and allows no or 1 through 9 decimals of seconds.
     *
     * Positive timezone may be indicated by plus or space, because plus tends
     * to become space when URL decoding.
     *
     * YYYY-MM-DDTHH:ii(:ss)?(.mmmmmmmmm)?(Z|+00:?(00)?)
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateTimeIso8601($var) : bool
    {
        $v = '' . $var;
        return strlen($v) <= 35
            && !!preg_match(
                '/^\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,9})?)?(Z|[ \+\-]\d{2}:?\d{0,2})$/',
                $v
            );
    }

    /**
     * Iso-8601 date which misses timezone indication.
     *
     * YYYY-MM-DD
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateIso8601Local($var) : bool
    {
        $v = '' . $var;
        return strlen($v) == 10 && !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * Doesn't contain tags.
     *
     * NB: Returns true on empty ('') string.
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function plainText($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : !strcmp($v, strip_tags($v));
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function ipAddress($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_IP);
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function url($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function httpUrl($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : (
            strpos($v, 'http') === 0
            && !!filter_var('' . $v, FILTER_VALIDATE_URL)
        );
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function email($var) : bool
    {
        // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
        $v = '' . $var;
        return $v === '' ? false : (
            !!filter_var($v, FILTER_VALIDATE_EMAIL)
            && !!preg_match('/\.[a-zA-Z\d]+$/', $v)
        );
    }
}
