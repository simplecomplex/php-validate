<?php

namespace SimpleComplex\Filter;

/**
 * Class Unicode
 *
 * @package SimpleComplex\Filter
 */
class Unicode {
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
     * Unicode constructor.
     */
    public function __construct() {
    }

    /**
     * @return static
     */
    public static function make() {
        // Make IDE recognize child class.
        /** @var Unicode */
        return new static();
    }

    /**
     * @var int
     */
    protected static $mbString = -1;

    /**
     * @return int
     *  0|1.
     */
    public static function nativeSupport() {
        $support = static::$mbString;
        if ($support == -1) {
            static::$mbString = $support = function_exists('mb_strlen');
        }
        return $support;
    }

    /**
     * Multibyte-safe string length.
     *
     * @param string $str
     *
     * @return int
     */
    public function strlen($str) {
        if ($str === '') {
            return 0;
        }
        if (static::nativeSupport()) {
            return mb_strlen($str);
        }

        $n = 0;
        $le = strlen($str);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($str{$i})) < 128) {
                ++$n;
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) {
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    ++$n;
                }
                $leading = true;
            }
        }
        return $n;
    }

    /**
     * Multibyte-safe sub string.
     *
     * @param string $str
     * @param int $start
     * @param int|null $length
     *  Default: null; until end of arg str.
     *
     * @return string
     */
    public function substr($str, $start, $length = null) {
        // Interprete non-null falsy length as zero.
        if ($str === '' || (!$length && $length !== null)) {
            return '';
        }
        if (static::nativeSupport()) {
            return !$length ? mb_substr($str, $start) : mb_substr($str, $start, $length);
        }

        // The actual algo (further down) only works when start is zero.
        if ($start > 0) {
            // Trim off chars before start.
            $str = substr($str, strlen($this->substr($str, 0, $start)));
        }
        // And the algo needs a length.
        if (!$length) {
            $length = $this->strlen($str);
        }

        $n = 0;
        $le = strlen($str);
        $leading = false;
        for ($i = 0; $i < $le; $i++) {
            // ASCII.
            if (($ord = ord($str{$i})) < 128) {
                if ((++$n) > $length) {
                    return substr($str, 0, $i);
                }
                $leading = false;
            }
            // Continuation char.
            elseif ($ord < 192) { // continuation char
                $leading = false;
            }
            // Leading char.
            else {
                // A sequence of leadings only counts as a single.
                if (!$leading) {
                    if ((++$n) > $length) {
                        return substr($str, 0, $i);
                    }
                }
                $leading = true;
            }
        }
        return $str;
    }

    /**
     * Truncate multibyte safe until ASCII length is equal to/less than arg
     * length.
     *
     * Does not check if arg $str is valid UTF-8.
     *
     * @param string $str
     * @param int $length
     *  Byte length (~ ASCII char length).
     *
     * @return string
     */
    public function truncateBytes($str, $length) {
        if (strlen($str) <= $length) {
            return $str;
        }

        // Truncate to UTF-8 char length (>= byte length).
        $str = $this->substr($str, 0, $length);
        // If all ASCII.
        if (($le = strlen($str)) == $length) {
            return $str;
        }

        // This algo will truncate one UTF-8 char too many,
        // if the string ends with a UTF-8 char, because it doesn't check
        // if a sequence of continuation bytes is complete.
        // Thus the check preceding this algo (actual byte length matches
        // required max length) is vital.
        do {
            --$le;
            // String not valid UTF-8, because never found an ASCII or leading UTF-8
            // byte to break before.
            if ($le < 0) {
                return '';
            }
            // An ASCII byte.
            elseif (($ord = ord($str{$le})) < 128) {
                // We can break before an ASCII byte.
                $ascii = true;
                $leading = false;
            }
            // A UTF-8 continuation byte.
            elseif ($ord < 192) {
                $ascii = $leading = false;
            }
            // A UTF-8 leading byte.
            else {
                $ascii = false;
                // We can break before a leading UTF-8 byte.
                $leading = true;
            }
        } while($le > $length || (!$ascii && !$leading));

        return substr($str, 0, $le);
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool|int
     */
    public function strpos($haystack, $needle) {
        if ($haystack === '' || $needle === '') {
            return false;
        }
        if (static::nativeSupport()) {
            return mb_strpos($haystack, $needle);
        }

        $pos = strpos($haystack, $needle);
        if (!$pos) {
            return $pos;
        }
        return count(
            preg_split('//u', substr($haystack, 0, $pos), null, PREG_SPLIT_NO_EMPTY)
        );
    }

}
