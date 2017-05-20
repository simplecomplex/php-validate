<?php

namespace SimpleComplex\Filter;

/**
 * Class Validate
 *
 * @package SimpleComplex\Filter
 */
class Validate {

  /**
   * List of previously instantiated objects, by name.
   *
   * @var array
   */
  protected static $instances = array();

  /**
   * @var Validate|null
   */
  protected static $lastInstance;

  /**
   * Validate constructor.
   */
  public function __construct() {
    static::$lastInstance = $this;
  }

  /**
   * @return static
   */
  public static function make() {
    // Make IDE recognize child class.
    /** @var Validate */
    return new static();
  }

  /**
   * @param string $name
   *
   * @return Validate
   */
  public static function getInstance($name = '') {
    if ($name) {
      if (isset(static::$instances[$name])) {
        return static::$instances[$name];
      }
    }
    elseif (static::$lastInstance) {
      return static::$lastInstance;
    }
    $nstnc = static::make();
    if ($name) {
      static::$instances[$name] = $nstnc;
    }
    return $nstnc;
  }

  // @todo: rename 'pattern' to 'rules'.

  // @todo: move all rules to a parent class.

  // @todo: get rid of $specs args, make list in parent class which tells how many arguments each rule method take.

  // @todo: handle non-rule flags; 'optional', 'buckets' ('children'?), 'exceptValue'|'or'|'orEnum'(?)

  /**
   *
   * @code
   * // Validate a value which should be an integer zero thru two.
   * $validate->pattern($some_input, [
   *   'integer',
   *   'range' => [
   *     0,
   *     2
   *   ]
   * ]);
   * @endcode
   *
   * @param mixed $value
   * @param array $pattern
   *   A list of rules; either 'rule':[specs] or N:rule.
   *   [
   *     'integer'
   *     'range': [ 0, 2 ]
   *   ]
   *
   * @return boolean
   */
  public function pattern($value, array $pattern) {
    return $this->internalPattern(0, $value, $pattern);
  }

  /**
   * @param integer $depth
   * @param mixed $value
   * @param array $pattern
   *
   * @return boolean
   */
  protected function internalPattern($depth, $value, array $pattern) {
    $buckets = NULL;
    foreach ($pattern as $k => $v) {
      // Bucket is simply the name of a rule; key is int, value is the rule.
      if (ctype_digit('' . $k)) {
        if (!$this->{$v}($value, array())) {
          return false;
        }
      }
      // Bucket key is name of the rule,
      // value is options or specifications for the rule.
      else {
        if ($k == 'buckets') {
          $buckets = $v;
          continue;
        }
        elseif ($k == 'optional') {
          continue;
        }
        if (!$this->{$k}($value, $v)) {
          return false;
        }
      }
    }
    if ($buckets) {
      // Prevent convoluted try-catches; only one at the top.
      if (!$depth) {
        try {
          return $this->internalBuckets(++$depth, $value, $buckets);
        }
        catch (\Exception $xc) {
          //
        }
      }
      else {
        return $this->internalBuckets(++$depth, $value, $buckets);
      }
    }
    return true;
  }

  /**
   * Recursive.
   *
   * @param array|object $collection
   * @param array $patterns
   *
   * @return boolean
   */
  protected function internalBuckets($depth, $collection, array $patterns) {
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
      case 'buckets':
        // buckets is a ...?
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
   * @param mixed $value
   *
   * @return boolean
   */
  public function empty($value) {
    if (!$value) {
      // Stringed zero - '0' - is not empty.
      return $value !== '0';
    }
    if (is_object($value) && !get_object_vars($value)) {
      return true;
    }
    return false;
  }

  /**
   * Compares type strict, and allowed values must be scalar or null.
   *
   * @param mixed $value
   * @param array $allowedScalarsNull
   *   [
   *     0: some scalar
   *     1: null
   *     3: other scalar
   *   ]
   *
   * @return boolean
   */
  public function enum($value, array $allowedScalarsNull) {
    if ($allowedScalarsNull) {
      foreach ($allowedScalarsNull as $allowed) {
        if ($value === $allowed) {
          return true;
        }
      }
      return false;
    }
  }

  /**
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|pattern: (str) /regular expression/
   *   ]
   *
   * @return boolean
   */
  public function regex($value, array $specs) {
    if ($specs) {
      return preg_match(reset($specs), '' . $value);
    }
    throw new \InvalidArgumentException('Missing args 0|pattern bucket.');
  }


  // Type checkers.-------------------------------------------------------------

  /**
   * @param mixed $value
   *
   * @return boolean
   */
  public function boolean($value) {
    return is_bool($value);
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
   * @param mixed $value
   *
   * @return boolean
   */
  public function number($value) {
    return is_int($value) || is_float($value);
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
   * @param mixed $value
   *
   * @return boolean
   */
  public function integer($value) {
    return is_int($value);
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
   * @param mixed $value
   *
   * @return boolean
   */
  public function float($value) {
    return is_float($value);
  }

  /**
   * @param mixed $value
   *
   * @return boolean
   */
  public function string($value) {
    return is_string($value);
  }

  /**
   * @param mixed $value
   *
   * @return boolean
   */
  public function null($value) {
    return $value === null;
  }

  /**
   * @param mixed $value
   *
   * @return boolean
   */
  public function object($value) {
    return is_object($value);
  }

  /**
   * @param mixed $value
   *
   * @return boolean
   */
  public function array($value) {
    return is_array($value);
  }

  /**
   * Array or object.
   *
   * NB: Not related to PHP>=7 \DS\Collection (Traversable, Countable,
   * JsonSerializable).
   *
   * @param mixed $value
   *
   * @return string|boolean
   *   String (array|object) on pass, boolean false on failure.
   */
  public function collection($value) {
    return is_array($value) ? 'array' : (is_object($value) ? 'object' : false);
  }


  // Numerically indexed arrays versus associative arrays (hast tables).--------

  /**
   * Does not check if the array's index is complete and correctly sequenced.
   *
   * @param mixed $value
   *
   * @return boolean
   *   True: empty array, or all keys are integers.
   */
  public function numArray($value) {
    if (!is_array($value)) {
      return false;
    }
    if (!$value) {
      return true;
    }
    return ctype_digit(join('', array_keys($value)));
  }

  /**
   * @param mixed $value
   *
   * @return boolean
   *   True: empty array, or at least one key is not integer.
   */
  public function assocArray($value) {
    if (!is_array($value)) {
      return false;
    }
    if (!$value) {
      return true;
    }
    return !ctype_digit(join('', array_keys($value)));
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function numeric($value) {
    $v = '' . $value;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function digit($value) {
    return ctype_digit('' . $value);
  }

  /**
   * Hexadeximal number (string).
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function hex($value) {
    return ctype_xdigit('' . $value);
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function bit32($value) {
    // Stringify for compatibility with numeric() and digit().
    $v = '' . $value;
    return $v >= -2147483648 && $v <= 2147483647;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function bit64($value) {
    // Stringify for compatibility with numeric() and digit().
    $v = '' . $value;
    return $v >= -9223372036854775808 && $v <= 9223372036854775807;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function positive($value) {
    // Stringify for compatibility with numeric() and digit().
    return '' . $value > 0;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function negative($value) {
    // Stringify for compatibility with numeric() and digit().
    return '' . $value < 0;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function nonNegative($value) {
    // Stringify for compatibility with numeric() and digit().
    return '' . $value >= 0;
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
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) minimum
   *   ]
   *
   * @return boolean
   */
  public function min($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return ('' . $value) >= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|min bucket.');
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
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|max: (int) maximum
   *   ]
   *
   * @return boolean
   */
  public function max($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return ('' . $value) <= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|max bucket.');
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
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) minimum
   *     1|max: (int) maximum
   *   ]
   *
   * @return boolean
   */
  public function range($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    $v = '' . $value;
    if (count($specs) > 1) {
      return $v >= reset($specs) && $v <= next($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|min and/or 1|max bucket.');
  }


  // UTF-8 string secondaries.--------------------------------------------------

  /**
   * Valid UTF-8.
   *
   * @see Validate::string()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function unicode($value) {
    $v = '' . $value;
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
   * @param mixed $value
   *
   * @return boolean
   */
  public function unicodePrintable($value) {
    $v = '' . $value;
    return !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
      && !strpos(' ' . $value, chr(127));
  }

  /**
   * Unicode printable that allows carriage return and newline.
   *
   * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
   *
   * @see Validate::string()
   * @see Validate::unicode()
   *
   * @param mixed $value
   *
   * @return boolean
   */
  public function unicodeMultiLine($value) {
    // Remove newline chars before checking if printable.
    return $this->unicodePrintable(str_replace(array("\r", "\n"), '', '' . $value));
  }

  /**
   * String minimum multibyte/Unicode length.
   *
   * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
   *
   * @see Validate::string()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) minimum length
   *   ]
   *
   * @return boolean
   */
  public function unicodeMinLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return Unicode::getInstance()->strlen('' . $value) >= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|min bucket.');
  }

  /**
   * String maximum multibyte/Unicode length.
   *
   * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
   *
   * @see Validate::string()
   * @see Validate::unicode()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) maximum length
   *   ]
   *
   * @return boolean
   */
  public function unicodeMaxLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return Unicode::getInstance()->strlen('' . $value) <= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|max bucket.');
  }

  /**
   * String exact multibyte/Unicode length.
   *
   * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
   *
   * @see Validate::string()
   * @see Validate::unicode()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) length
   *   ]
   *
   * @return boolean
   */
  public function unicodeExactLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return Unicode::getInstance()->strlen('' . $value) == reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|length bucket.');
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function ascii($value) {
    return preg_match('/^[[:ascii:]]+$/', '' . $value);
  }

  /**
   * Allows ASCII except lower ASCII and DEL.
   *
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function asciiPrintable($value) {
    $v = '' . $value;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function asciiMultiLine($value) {
    // Remove newline chars before checking if printable.
    return $this->asciiPrintable(str_replace(array("\r", "\n"), '', '' . $value));
  }

  /**
   * ASCII lowercase.
   *
   * NB: Does not check if ASCII; use 'ascii' (or stricter) rule before this.
   *
   * @see Validate::ascii()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function asciiLowerCase($value) {
    // ctype_... is no good for ASCII-only check, if PHP and server locale
    // is set to something non-English.
    return ctype_lower('' . $value);
  }

  /**
   * ASCII uppercase.
   *
   * NB: Does not check if ASCII; use 'ascii' (or stricter) rule before this.
   *
   * @see Validate::ascii()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function asciiUpperCase($value) {
    // ctype_... is no good for ASCII-only check, if PHP and server locale
    // is set to something non-English.
    return ctype_upper('' . $value);
  }

  /**
   * String minimum byte/ASCII length.
   *
   * @see Validate::string()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) minimum length
   *   ]
   *
   * @return boolean
   */
  public function minLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return strlen('' . $value) >= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|min bucket.');
  }

  /**
   * String maximum byte/ASCII length.
   *
   * @see Validate::string()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) maximum length
   *   ]
   *
   * @return boolean
   */
  public function maxLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return strlen('' . $value) <= reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|max bucket.');
  }

  /**
   * String exact byte/ASCII length.
   *
   * @see Validate::string()
   *
   * @param mixed $value
   *   Gets stringified.
   * @param array $specs
   *   [
   *     0|min: (int) length
   *   ]
   *
   * @return boolean
   */
  public function exactLength($value, array $specs) {
    // Stringify for compatibility with numeric() and digit().
    if ($specs) {
      return strlen('' . $value) == reset($specs);
    }
    throw new \InvalidArgumentException('Missing args 0|length bucket.');
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function alphaNum($value) {
    // ctype_... is no good for ASCII-only check, if PHP and server locale
    // is set to something non-English.
    return preg_match('/^[a-zA-Z\d]+$/', '' . $value);
  }

  /**
   * Name; must start with alpha or underscore, followed by alphanum/underscore.
   *
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function name($value) {
    return preg_match('/^[a-zA-Z_][a-zA-Z\d_]*$/', '' . $value);
  }

  /**
   * Dashed name; must start with alpha, followed by alphanum/dash.
   *
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function dashName($value) {
    return preg_match('/^[a-zA-Z][a-zA-Z\d\-]*$/', '' . $value);
  }

  /**
   * @see Validate::string()
   *
   * @see Validate::asciiLowerCase()
   * @see Validate::asciiUpperCase()
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function uuid($value) {
    $v = '' . $value;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function base64($value) {
    return preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $value);
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function dateTimeIso8601($value) {
    $v = '' . $value;
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
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function dateIso8601Local($value) {
    $v = '' . $value;
    return strlen($v) == 10 && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
  }


  // Character set indifferent specials.----------------------------------------

  /**
   * Doesn't contain tags.
   *
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function plainText($value) {
    $v = '' . $value;
    return !strcmp($v, strip_tags($v));
  }

  /**
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function ipAddress($value) {
    return !!filter_var('' . $value, FILTER_VALIDATE_IP);
  }

  /**
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function url($value) {
    return !!filter_var('' . $value, FILTER_VALIDATE_URL);
  }

  /**
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function httpUrl($value) {
    $v = '' . $value;
    return strpos($v, 'http') === 0 && !!filter_var('' . $v, FILTER_VALIDATE_URL);
  }

  /**
   * @param mixed $value
   *   Gets stringified.
   *
   * @return boolean
   */
  public function email($value) {
    $v = '' . $value;
    // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
    return !!filter_var($v, FILTER_VALIDATE_EMAIL) && preg_match('/\.[a-zA-Z\d]+$/', $v);
  }
}
