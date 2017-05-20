<?php
/**
 * Created by PhpStorm.
 * User: jacob
 * Date: 20/05/17
 * Time: 19:14
 */

namespace SimpleComplex\Filter;

/**
 * Class Sanitize
 *
 * @package SimpleComplex\Filter
 */
class Sanitize {

  /**
   * List of previously instantiated objects, by name.
   *
   * @var array
   */
  protected static $instances = array();

  /**
   * @var Sanitize|null
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
    /** @var Sanitize */
    return new static();
  }

  /**
   * @param string $name
   *
   * @return Sanitize
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

  /**
   * Remove tags, escape HTML entities, and remove invalid UTF-8 sequences.
   *
   * @param mixed $str
   *   Gets stringified.
   *
   * @return string
   */
  public function plainText($str) {
    return htmlspecialchars(strip_tags('' . $str), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * Full ASCII; 0-127.
   *
   * @param mixed $str
   *   Gets stringified.
   *
   * @return boolean
   */
  public function ascii($str) {
    return preg_replace('/[^[:ascii:]]/', '', '' . $str);
  }

  /**
   * ASCII except lower ASCII and DEL.
   *
   * @param mixed $str
   *   Gets stringified.
   *
   * @return string
   */
  public function asciiPrintable($str) {
    return str_replace(
      chr(127),
      '',
      filter_var('' . $str, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH)
    );
  }

  // @todo: add parameter $carriageReturn = false(?)
  /**
   * ASCII printable that allows newline and carriage return.
   *
   * @param mixed $str
   *   Gets stringified.
   *
   * @return boolean
   */
  public function asciiMultiLine($str) {
    // Remove lower ASCII except newline \x0A and CR \x0D,
    // and remove DEL and upper range.
    return preg_replace(
      '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/',
      '',
      filter_var('' . $str, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH)
    );
  }

  /**
   * Allows anything but lower ASCII and DEL.
   *
   * @param string $str
   *
   * @return string
   */
  public function unicodePrintable($str) {
    return str_replace(
      chr(127),
      '',
      filter_var('' . $str, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)
    );
  }

  /**
   * Unicode printable that allows carriage return and newline.
   *
   * @param string $str
   *
   * @return string
   */
  public function unicodeMultiline($str) {
    // Remove lower ASCII except newline \x0A and CR \x0D, and remove DEL.
    return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', '' . $str);
  }
}
