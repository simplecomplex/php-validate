<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerAwareTrait;
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
   * @function getInstance()
   * @see GetInstanceTrait::getInstance()
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
   *   PSR-3 logger, if any.
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
   *   A list of rules; either 'rule':[specs] or N:rule.
   *   [
   *     'integer'
   *     'range': [ 0, 2 ]
   *   ]
   *
   * @return boolean
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
   * @param integer $depth
   * @param mixed $var
   * @param array $ruleSet
   *
   * @return boolean
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
   * @return boolean
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


  // Type indifferent.----------------------------------------------------------

  /**
   * Stringed zero - '0' - is not empty.
   *
   * @param mixed $var
   *
   * @return boolean
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
   *   [
   *     0: some scalar
   *     1: null
   *     3: other scalar
   *   ]
   *
   * @return boolean
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
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|pattern: (str) /regular expression/
   *   ]
   *
   * @return boolean
   */
  public function regex($var, array $specs) {
    if ($specs) {
      return preg_match(reset($specs), '' . $var);
    }
    throw new \InvalidArgumentException('Missing args 0|pattern bucket.');
  }


  // Type checkers.-------------------------------------------------------------

  /**
   * @param mixed $var
   *
   * @return boolean
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
   * @return boolean
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
   * @return boolean
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
   * @return boolean
   */
  public function float($var) {
    return is_float($var);
  }

  /**
   * @param mixed $var
   *
   * @return boolean
   */
  public function string($var) {
    return is_string($var);
  }

  /**
   * @param mixed $var
   *
   * @return boolean
   */
  public function null($var) {
    return $var === null;
  }

  /**
   * @param mixed $var
   *
   * @return boolean
   */
  public function object($var) {
    return is_object($var);
  }

  /**
   * @param mixed $var
   *
   * @return boolean
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
   * @return string|boolean
   *   String (array|object) on pass, boolean false on failure.
   */
  public function collection($var) {
    return is_array($var) ? 'array' : (is_object($var) ? 'object' : false);
  }


  // Numerically indexed arrays versus associative arrays (hast tables).--------

  /**
   * Does not check if the array's index is complete and correctly sequenced.
   *
   * @param mixed $var
   *
   * @return boolean
   *   True: empty array, or all keys are integers.
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
   * @return boolean
   *   True: empty array, or at least one key is not integer.
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


  // Numbers or stringed numbers.-----------------------------------------------

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
   * @return boolean
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
   * @return boolean
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
   * @return boolean
   */
  public function hex($var) {
    return ctype_xdigit('' . $var);
  }


  // Numeric secondaries.-------------------------------------------------------

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
   * @return boolean
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
   * @return boolean
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
   * @return boolean
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
   * @return boolean
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
   * @return boolean
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
   * @param integer|float $min
   *
   * @return boolean
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
   * @param integer|float $max
   *
   * @return boolean
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
   * @param integer|float $min
   * @param integer|float $max
   *
   * @return boolean
   */
  public function range($var, $min, $max) {
    return $var >= $min && $var <= $max;
  }


  // UTF-8 string secondaries.--------------------------------------------------

  /**
   * Valid UTF-8.
   *
   * @see Validate::string()
   *
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
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
   * @return boolean
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
   * @return boolean
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
   *   Gets stringified.
   * @param integer $min
   *
   * @return boolean
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
   *   Gets stringified.
   * @param integer $max
   *
   * @return boolean
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
   *   Gets stringified.
   * @param integer $exact
   *
   * @return boolean
   */
  public function unicodeExactLength($var, $exact) {
    return Unicode::getInstance()->strlen('' . $var) == $exact;
  }


  // ASCII string secondaries.--------------------------------------------------

  /**
   * Full ASCII; 0-127.
   *
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   * @param integer $min
   *
   * @return boolean
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
   *   Gets stringified.
   * @param integer $max
   *
   * @return boolean
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
   *   Gets stringified.
   * @param integer $exact
   *
   * @return boolean
   */
  public function exactLength($var, $exact) {
    return strlen('' . $var) == $exact;
  }


  // ASCII specials.------------------------------------------------------------

  /**
   * ASCII alphanumeric.
   *
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
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
   *   Gets stringified.
   *
   * @return boolean
   */
  public function dateIso8601Local($var) {
    $v = '' . $var;
    return strlen($v) == 10 && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
  }


  // Character set indifferent specials.----------------------------------------

  /**
   * Doesn't contain tags.
   *
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
   */
  public function plainText($var) {
    $v = '' . $var;
    return !strcmp($v, strip_tags($v));
  }

  /**
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
   */
  public function ipAddress($var) {
    return !!filter_var('' . $var, FILTER_VALIDATE_IP);
  }

  /**
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
   */
  public function url($var) {
    return !!filter_var('' . $var, FILTER_VALIDATE_URL);
  }

  /**
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
   */
  public function httpUrl($var) {
    $v = '' . $var;
    return strpos($v, 'http') === 0 && !!filter_var('' . $v, FILTER_VALIDATE_URL);
  }

  /**
   * @param mixed $var
   *   Gets stringified.
   *
   * @return boolean
   */
  public function email($var) {
    $v = '' . $var;
    // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
    return !!filter_var($v, FILTER_VALIDATE_EMAIL) && preg_match('/\.[a-zA-Z\d]+$/', $v);
  }
}
