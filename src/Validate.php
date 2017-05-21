<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Class Validate
 *
 * MAXIMUM NUMBER OF RULE METHOD PARAMETERS
 * A rule method is not allowed to have more than 5 parameters,
 * that is: 1 for the var to validate and max. 4 secondary
 * (specifying) parameters.
 * ValidateByRules::challenge() will err when given more than 4 secondary args.
 *
 * BAD RULE METHOD ARGS CHECK
 * Rule methods that take more arguments than the $var to validate:
 * - must check those arguments for type (and emptyness, if required)
 * - must log meaningful error message (if logger) and return false, or throw exception
 * Reasons:
 * - type hints don't support someType|otherType&not-empty
 * - an exception thrown because of type hint conflict may not be simple to handle and comprehend
 * - we prefer to fail gracefully; a non-passed validation should stop the party
 *   in a well constructed application anyway
 *
 *
 *
 * @package SimpleComplex\Filter
 */
class Validate implements ValidationRuleProviderInterface {
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
     * @function getInstance()
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
     * Methods of this class that a ValidateByRules instance should never call.
     *
     * Does not contain __construct nor __call; would be slightly paranoid.
     *
     * @var array
     */
    const NON_RULE_METHODS = [
        'getInstance',
        'flushInstance',
        'make',
        'getNonRuleMethods',
        'getLogger',
    ];

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var array
     */
    protected $nonRuleMethods = [];

    /**
     * Validate constructor.
     *
     * When provided with a logger, rule methods will fail gracefully
     * when given wrong secondary argument(s) - otherwise they throw exception.
     *
     * @param LoggerInterface|null
     *  PSR-3 logger, if any.
     */
    public function __construct($logger = null) {
        $this->logger = $logger;

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
     * @param LoggerInterface|null
     *
     * @return static
     */
    public static function make($logger = null) {
        // Make IDE recognize child class.
        /** @var Validate */
        return new static($logger);
    }

    /**
     * Makes our logger available for a ValidateByRules instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger() {
        return $this->logger;
    }

    /**
     * @return array
     */
    public function getNonRuleMethods() : array {
        if (!$this->nonRuleMethods) {
            $this->nonRuleMethods = self::NON_RULE_METHODS;
        }
        return $this->nonRuleMethods;
    }

    /**
     * @throws \BadMethodCallException
     *
     * @param string $name
     * @param array $arguments
     */
    public function __call($name, $arguments) {
        if ($this->logger) {
            // Log warning instaed of error, because we also throw an exception.
            $this->logger->warning('Class ' . get_called_class() . ' provides no rule \'{rule_method}\'.', [
                'type' => static::LOG_TYPE,
                'rule_method' => $name,
            ]);
        }
        throw new \BadMethodCallException('Undefined validation rule[' . $name . '].');
    }


    // Rules.-----------------------------------------------------------------------------------------------------------

    // Type indifferent.--------------------------------------------------------

    /**
     * Stringed zero - '0' - is not empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function empty($var) {
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
     * Compares type strict, and allowed values must be scalar or null.
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     * @param array $allowedValues
     *  [
     *    0: some scalar
     *    1: null
     *    3: other scalar
     *  ]
     *
     * @return bool
     */
    public function enum($var, $allowedValues) {
        if (!$allowedValues || !is_array($allowedValues)) {
            $msg = 'allowedValues is not non-empty array.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'allowedValues' => $allowedValues,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        foreach ($allowedValues as $allowed) {
            if ($var === $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param string $pattern
     *  /regular expression/
     *
     * @return bool
     */
    public function regex($var, $pattern) {
        if (!$pattern || !is_string($pattern)) {
            $msg = 'pattern is not non-empty string.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'pattern' => $pattern,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return preg_match($pattern, '' . $var);
    }

    // Type checkers.-----------------------------------------------------------

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function boolean($var) {
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
     * @return bool
     */
    public function number($var) {
        return is_int($var) || is_float($var);
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
    public function integer($var) {
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
    public function float($var) {
        return is_float($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function string($var) {
        return is_string($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function null($var) {
        return $var === null;
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function object($var) {
        return is_object($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function array($var) {
        return is_array($var);
    }

    /**
     * Array or object.
     *
     * NB: Not related to PHP>=7 \DS\Collection (Traversable, Countable,
     * JsonSerializable).
     *
     * @param mixed $var
     *
     * @return string|bool
     *  String (array|object) on pass, boolean false on failure.
     */
    public function collection($var) {
        return is_array($var) ? 'array' : (is_object($var) ? 'object' : false);
    }


    // Numerically indexed arrays versus associative arrays (hast tables).------

    /**
     * Does not check if the array's index is complete and correctly sequenced.
     *
     * @param mixed $var
     *
     * @return bool
     *  True: empty array, or all keys are integers.
     */
    public function numArray($var) {
        if (!is_array($var)) {
            return false;
        }
        if (!$var) {
            return true;
        }
        return ctype_digit(join('', array_keys($var)));
    }

    /**
     * @param mixed $var
     *
     * @return bool
     *  True: empty array, or at least one key is not integer.
     */
    public function assocArray($var) {
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
     * Integer, float or stringed integer/float (not empty string).
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
     *
     * @return bool
     */
    public function numeric($var) {
        $v = '' . $var;
        if (strpos($v, '.') !== FALSE) {
            $count = 0;
            $v = str_replace('.', '', $v, $count);
            if ($count != 1) {
                return false;
            }
        }
        return ctype_digit($v);
    }

    /**
     * Stringed integer.
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
     *
     * @return bool
     */
    public function digit($var) {
        return ctype_digit('' . $var);
    }

    /**
     * Hexadeximal number (string).
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function hex($var) {
        return ctype_xdigit('' . $var);
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
    public function bit32($var) {
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
    public function bit64($var) {
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
    public function positive($var) {
        return $var > 0;
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
    public function negative($var) {
        return $var < 0;
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
    public function nonNegative($var) {
        return $var >= 0;
    }

    /**
     * Numeric minimum.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     * @param int|float $min
     *  Stringed number is not accepted.
     *
     * @return bool
     */
    public function min($var, $min) {
        if (!is_int($min) && !is_float($min)) {
            $msg = 'min is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'min' => $min,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return $var >= $min;
    }

    /**
     * Numeric maximum.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     * @param int|float $max
     *  Stringed number is not accepted.
     *
     * @return bool
     */
    public function max($var, $max) {
        if (!is_int($max) && !is_float($max)) {
            $msg = 'max is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'max' => $max,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return $var <= $max;
    }

    /**
     * Numeric range.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     * @param int|float $min
     *  Stringed number is not accepted.
     * @param int|float $max
     *  Stringed number is not accepted.
     *
     * @return bool
     */
    public function range($var, $min, $max) {
        if (!is_int($min) && !is_float($min)) {
            $msg = 'min is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'min' => $min,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }
        if (!is_int($max) && !is_float($max)) {
            $msg = 'max is not integer or float.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'max' => $max,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return $var >= $min && $var <= $max;
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * Valid UTF-8.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function unicode($var) {
        $v = '' . $var;
        return $v === '' ? TRUE :
            // The PHP regex u modifier forces the whole subject to be evaluated
            // as UTF-8. And if any byte sequence isn't valid UTF-8 preg_match()
            // will return zero for no-match.
            // The s modifier makes dot match newline; without it a string consisting
            // of a newline solely would result in a false negative.
            preg_match('/./us', $v);
    }

    /**
     * Allows anything but lower ASCII and DEL.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function unicodePrintable($var) {
        $v = '' . $var;
        return !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
            && !strpos(' ' . $var, chr(127));
    }

    /**
     * Unicode printable that allows carriage return and newline.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function unicodeMultiLine($var) {
        // Remove newline chars before checking if printable.
        return $this->unicodePrintable(str_replace(["\r", "\n"], '', '' . $var));
    }

    /**
     * String minimum multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $min
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeMinLength($var, $min) {
        if (!is_int($min)) {
            $msg = 'min is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'min' => $min,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return Unicode::getInstance()->strlen('' . $var) >= $min;
    }

    /**
     * String maximum multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $max
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeMaxLength($var, $max) {
        if (!is_int($max)) {
            $msg = 'max is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'max' => $max,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return Unicode::getInstance()->strlen('' . $var) <= $max;
    }

    /**
     * String exact multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $exact
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function unicodeExactLength($var, $exact) {
        if (!is_int($exact)) {
            $msg = 'exact is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'exact' => $exact,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return Unicode::getInstance()->strlen('' . $var) == $exact;
    }


    // ASCII string secondaries.------------------------------------------------

    /**
     * Full ASCII; 0-127.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function ascii($var) {
        return preg_match('/^[[:ascii:]]+$/', '' . $var);
    }

    /**
     * Allows ASCII except lower ASCII and DEL.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function asciiPrintable($var) {
        $v = '' . $var;
        return !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH))
            && !strpos(' ' . $v, chr(127));
    }

    /**
     * ASCII printable that allows carriage return and newline.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function asciiMultiLine($var) {
        // Remove newline chars before checking if printable.
        return $this->asciiPrintable(str_replace(["\r", "\n"], '', '' . $var));
    }

    /**
     * ASCII lowercase.
     *
     * NB: Does not check if ASCII; use 'ascii' (or stricter) rule before this.
     *
     * @see Validate::ascii()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function asciiLowerCase($var) {
        // ctype_... is no good for ASCII-only check, if PHP and server locale
        // is set to something non-English.
        return ctype_lower('' . $var);
    }

    /**
     * ASCII uppercase.
     *
     * NB: Does not check if ASCII; use 'ascii' (or stricter) rule before this.
     *
     * @see Validate::ascii()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function asciiUpperCase($var) {
        // ctype_... is no good for ASCII-only check, if PHP and server locale
        // is set to something non-English.
        return ctype_upper('' . $var);
    }

    /**
     * String minimum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $min
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function minLength($var, $min) {
        if (!is_int($min)) {
            $msg = 'min is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'min' => $min,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return strlen('' . $var) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $max
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function maxLength($var, $max) {
        if (!is_int($max)) {
            $msg = 'max is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'max' => $max,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return strlen('' . $var) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @throws \InvalidArgumentException
     *  Unless logger.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $exact
     *  Stringed integer is not accepted.
     *
     * @return bool
     */
    public function exactLength($var, $exact) {
        if (!is_int($exact)) {
            $msg = 'exact is not integer.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variables' => [
                        'exact' => $exact,
                    ],
                ]);
            } else {
                throw new \InvalidArgumentException('Arg ' . $msg);
            }
            // Important.
            return false;
        }

        return strlen('' . $var) == $exact;
    }


    // ASCII specials.----------------------------------------------------------

    /**
     * ASCII alphanumeric.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function alphaNum($var) {
        // ctype_... is no good for ASCII-only check, if PHP and server locale
        // is set to something non-English.
        return preg_match('/^[a-zA-Z\d]+$/', '' . $var);
    }

    /**
     * Name; must start with alpha or underscore, followed by alphanum/underscore.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function name($var) {
        return preg_match('/^[a-zA-Z_][a-zA-Z\d_]*$/', '' . $var);
    }

    /**
     * Dashed name; must start with alpha, followed by alphanum/dash.
     *
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function dashName($var) {
        return preg_match('/^[a-zA-Z][a-zA-Z\d\-]*$/', '' . $var);
    }

    /**
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function uuid($var) {
        $v = '' . $var;
        return strlen($v) == 36
            && preg_match(
                '/^[\da-fA-F]{8}\-[\da-fA-F]{4}\-[\da-fA-F]{4}\-[\da-fA-F]{4}\-[\da-fA-F]{12}$/',
                $v
            );
    }

    /**
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function base64($var) {
        return preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $var);
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
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function dateTimeIso8601($var) {
        $v = '' . $var;
        return strlen($v) <= 35
            && preg_match(
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
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function dateIso8601Local($var) {
        $v = '' . $var;
        return strlen($v) == 10 && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * Doesn't contain tags.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function plainText($var) {
        $v = '' . $var;
        return !strcmp($v, strip_tags($v));
    }

    /**
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function ipAddress($var) {
        return !!filter_var('' . $var, FILTER_VALIDATE_IP);
    }

    /**
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function url($var) {
        return !!filter_var('' . $var, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function httpUrl($var) {
        $v = '' . $var;
        return strpos($v, 'http') === 0 && !!filter_var('' . $v, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $var
     *  Gets stringified.
     *
     * @return bool
     */
    public function email($var) {
        $v = '' . $var;
        // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
        return !!filter_var($v, FILTER_VALIDATE_EMAIL) && preg_match('/\.[a-zA-Z\d]+$/', $v);
    }
}
