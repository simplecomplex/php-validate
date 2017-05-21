<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Class Validate
 *
 * @package SimpleComplex\Filter
 */
class Validate {
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
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Validate constructor.
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


    // Catch-all.---------------------------------------------------------------

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
    public function enum($var, array $allowedValues) {
        foreach ($allowedValues as $allowed) {
            if ($var === $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $var
     *  Gets stringified.
     * @param array $specs
     *  [
     *    0|pattern: (str) /regular expression/
     *  ]
     *
     * @return bool
     */
    public function regex($var, array $specs) {
        if ($specs) {
            return preg_match(reset($specs), '' . $var);
        }
        throw new \InvalidArgumentException('Missing args 0|pattern bucket.');
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
     * @param mixed $var
     * @param int|float $min
     *
     * @return bool
     */
    public function min($var, $min) {
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
     * @param mixed $var
     * @param int|float $max
     *
     * @return bool
     */
    public function max($var, $max) {
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
     * @param mixed $var
     * @param int|float $min
     * @param int|float $max
     *
     * @return bool
     */
    public function range($var, $min, $max) {
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
     * @see Validate::string()
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
     * @see Validate::string()
     * @see Validate::unicode()
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function unicodeMultiLine($var) {
        // Remove newline chars before checking if printable.
        return $this->unicodePrintable(str_replace(array("\r", "\n"), '', '' . $var));
    }

    /**
     * String minimum multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $min
     *
     * @return bool
     */
    public function unicodeMinLength($var, $min) {
        return Unicode::getInstance()->strlen('' . $var) >= $min;
    }

    /**
     * String maximum multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::string()
     * @see Validate::unicode()
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $max
     *
     * @return bool
     */
    public function unicodeMaxLength($var, $max) {
        return Unicode::getInstance()->strlen('' . $var) <= $max;
    }

    /**
     * String exact multibyte/Unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::string()
     * @see Validate::unicode()
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $exact
     *
     * @return bool
     */
    public function unicodeExactLength($var, $exact) {
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
        return $this->asciiPrintable(str_replace(array("\r", "\n"), '', '' . $var));
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
     * @param mixed $var
     *  Gets stringified.
     * @param int $min
     *
     * @return bool
     */
    public function minLength($var, $min) {
        return strlen('' . $var) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $max
     *
     * @return bool
     */
    public function maxLength($var, $max) {
        return strlen('' . $var) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *  Gets stringified.
     * @param int $exact
     *
     * @return bool
     */
    public function exactLength($var, $exact) {
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
