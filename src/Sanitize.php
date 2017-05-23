<?php

namespace SimpleComplex\Filter;

/**
 * Class Sanitize
 *
 * @package SimpleComplex\Filter
 */
class Sanitize {
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
     * @var static $lastInstance
     *
     * Get previously instantiated object or create new.
     * @public
     * @static
     * @see GetInstanceTrait::getInstance()
     *
     * Kill class reference(s) to instance(s).
     * @public
     * @static
     * @see GetInstanceTrait::flushInstance()
     */
    use GetInstanceTrait;

    /**
     * Validate constructor.
     */
    public function __construct() {
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
     * Remove tags, escape HTML entities, and remove invalid UTF-8 sequences.
     *
     * @param mixed $str
     *  Gets stringified.
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
     *  Gets stringified.
     *
     * @return bool
     */
    public function ascii($str) {
        return preg_replace('/[^[:ascii:]]/', '', '' . $str);
    }

    /**
     * ASCII except lower ASCII and DEL.
     *
     * @param mixed $str
     *  Gets stringified.
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
     *  Gets stringified.
     *
     * @return bool
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
