<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Traits;

use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * Pattern rules - non type-checking implementation.
 *
 * Methods which don't check subject's type before testing the actual rule.
 *
 * Equivalent interface:
 * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface
 *
 * @package SimpleComplex\Validate
 */
trait PatternRulesUncheckedTrait
{
    /**
     * Subject strictly equal to a bucket of an array.
     *
     * Beware: Does not check whether all allowed values are equatable.
     * @see TypeRulesTrait::equatable()
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar (not float)
     *          1: null
     *          3: other scalar (not float)
     *      ]
     *
     * @return bool
     */
    public function enum($subject, array $allowedValues) : bool
    {
        return in_array($subject, $allowedValues, true);
    }

    // Numeric secondaries.-----------------------------------------------------

    /**
     * 32-bit numeric.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit32($subject) : bool
    {
        return $subject >= -2147483648 && $subject <= 2147483647;
    }

    /**
     * 64-bit numeric.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit64($subject) : bool
    {
        return $subject >= -9223372036854775808 && $subject <= 9223372036854775807;
    }

    /**
     * Positive numeric; not zero and not negative.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function positive($subject) : bool
    {
        return $subject > 0;
    }

    /**
     * Zero or positive numeric.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function nonNegative($subject) : bool
    {
        return $subject >= 0;
    }

    /**
     * Negative numeric; not zero and not positive.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function negative($subject) : bool
    {
        return $subject < 0;
    }

    /**
     * Numeric minimum.
     *
     * May produce false negative if args subject and min both are float;
     * comparing floats is inherently imprecise.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     * @param int|float $min
     *      Stringed number is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not integer or float.
     */
    public function min($subject, $min) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            throw new InvalidArgumentException('Arg min type[' . Helper::getType($min) . '] is not integer or float.');
        }
        return $subject >= $min;
    }

    /**
     * Numeric maximum.
     *
     * May produce false negative if args subject and max both are float;
     * comparing floats is inherently imprecise.
     *
     * @param mixed $subject
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not integer or float.
     */
    public function max($subject, $max) : bool
    {
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException('Arg max type[' . Helper::getType($max) . '] is not integer or float.');
        }
        return $subject <= $max;
    }

    /**
     * Numeric range.
     *
     * May produce false negative if (at least) two of the args are float;
     * comparing floats is inherently imprecise.
     *
     * @see TypeRulesTrait::numeric()
     *
     * @param mixed $subject
     * @param int|float $min
     *      Stringed number is not accepted.
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not integer or float.
     *      Arg max not integer or float.
     *      Arg max less than arg min.
     */
    public function range($subject, $min, $max) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            throw new InvalidArgumentException('Arg min type[' . Helper::getType($min) . '] is not integer or float.');
        }
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException('Arg max type[' . Helper::getType($max) . '] is not integer or float.');
        }
        if ($max < $min) {
            throw new InvalidArgumentException('Arg max[' .  $max . '] cannot be less than arg min[' .  $min . '].');
        }
        return $subject >= $min && $subject <= $max;
    }


    // String character set indifferent.----------------------------------------

    /**
     * Matches regular expression.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $pattern
     *      /regular expression/
     *      /regular expression/modifier
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg pattern empty, or isn't delimited by forward slash.
     */
    public function regex($subject, string $pattern) : bool
    {
        if (!$pattern || $pattern[0] !== '/') {
            throw new InvalidArgumentException('Arg pattern ' . (!$pattern ? 'is empty.' : 'is not slash delimited.'));
        }
        return !!preg_match($pattern, '' . $subject);
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * Valid UTF-8.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function unicode($subject) : bool
    {
        $v = '' . $subject;
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
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function unicodePrintable($subject) : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return true;
        }
        // Check unicode.
        if (!preg_match('/./us', $v)) {
            return false;
        }
        // filter_var() is not so picky about it's return value :-(
        if (
            !($filtered = filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
            || !is_string($filtered)
        ) {
            return false;
        }
        // Filtered equals raw, and no DEL char.
        // strcmp() equality is zero.
        return !strcmp($v, $filtered)
            && strpos($v, chr(127)) === false;
    }

    /**
     * Unicode printable that allows newline and (default) carriage return.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function unicodeMultiLine($subject, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->unicodePrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $subject
            )
        );
    }

    /**
     * String minimum multibyte/unicode length.
     *
     * Beware: Does not check if valid UTF-8; use 'unicode' rule before this.
     * @see unicode()
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not non-negative.
     */
    public function unicodeMinLength($subject, int $min) : bool
    {
        if ($min < 0) {
            throw new InvalidArgumentException('Arg min[' . $min . '] is not non-negative.');
        }
        $v = '' . $subject;
        if ($v === '') {
            return $min == 0;
        }
        return mb_strlen($v) >= $min;
    }

    /**
     * String maximum multibyte/unicode length.
     *
     * Beware: Does not check if valid UTF-8; use 'unicode' rule before this.
     * @see unicode()
     *
     * @see stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not non-negative.
     */
    public function unicodeMaxLength($subject, int $max) : bool
    {
        if ($max < 0) {
            throw new InvalidArgumentException('Arg max[' . $max . '] is not non-negative.');
        }
        $v = '' . $subject;
        if ($v === '') {
            return true;
        }
        return mb_strlen($v) <= $max;
    }

    /**
     * String exact multibyte/unicode length.
     *
     * Beware: Does not check if valid UTF-8; use 'unicode' rule before this.
     * @see unicode()
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg exact not non-negative.
     */
    public function unicodeExactLength($subject, int $exact) : bool
    {
        if ($exact < 0) {
            throw new InvalidArgumentException('Arg exact[' . $exact . '] is not non-negative.');
        }
        $v = '' . $subject;
        if ($v === '') {
            return $exact == 0;
        }
        return mb_strlen($v) == $exact;
    }


    // ASCII string secondaries.------------------------------------------------

    /**
     * Hexadeximal number (string).
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function hex($subject, string $case = '') : bool
    {
        switch ($case) {
            case 'lower':
                $v = '' . $subject;
                return $v === '' ? false : !!preg_match('/^[a-f\d]+$/', '' . $v);
            case 'upper':
                $v = '' . $subject;
                return $v === '' ? false : !!preg_match('/^[A-F\d]+$/', '' . $v);
        }
        // Yes, ctype_... returns false on ''.
        return !!ctype_xdigit('' . $subject);
    }

    /**
     * Full ASCII; 0-127.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function ascii($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? true : !!preg_match('/^[[:ascii:]]+$/', $v);
    }

    /**
     * Allows ASCII except lower ASCII and DEL.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function asciiPrintable($subject) : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return true;
        }
        // filter_var() is not so picky about it's return value :-(
        if (
            !($filtered = filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH))
            || !is_string($filtered)
        ) {
            return false;
        }
        // Filtered equals raw, and no DEL char.
        // strcmp() equality is zero.
        return !strcmp($v, $filtered)
            && strpos($v, chr(127)) === false;
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function asciiMultiLine($subject, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->asciiPrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $subject
            )
        );
    }

    /**
     * String minimum byte/ASCII length.
     *
     * Beware: Does not check if ascii; use 'ascii' rule before this.
     * @see ascii()
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not non-negative.
     */
    public function minLength($subject, int $min) : bool
    {
        if ($min < 0) {
            throw new InvalidArgumentException('Arg min[' . $min . '] is not non-negative.');
        }
        return strlen('' . $subject) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * Beware: Does not check if ascii; use 'ascii' rule before this.
     * @see ascii()
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not non-negative.
     */
    public function maxLength($subject, int $max) : bool
    {
        if ($max < 0) {
            throw new InvalidArgumentException('Arg max[' . $max . '] is not non-negative.');
        }
        return strlen('' . $subject) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * Beware: Does not check if ascii; use 'ascii' rule before this.
     * @see ascii()
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg exact not non-negative.
     */
    public function exactLength($subject, int $exact) : bool
    {
        if ($exact < 0) {
            throw new InvalidArgumentException('Arg exact[' . $exact . '] is not non-negative.');
        }
        return strlen('' . $subject) == $exact;
    }


    // ASCII specials.----------------------------------------------------------

    /**
     * ASCII alphanumeric.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function alphaNum($subject, string $case = '') : bool
    {
        $v = '' . $subject;
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
     * Name: starts with alpha, followed by alphanum/underscore/hyphen.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function name($subject, string $case = '') : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z][a-z\d_\-]*$/';
                break;
            case 'upper':
                $regex = '/^[A-Z][A-Z\d_\-]*$/';
                break;
            default:
                $regex = '/^[a-zA-Z][a-zA-Z\d_\-]*$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * Camel cased name: starts with alpha, followed by alphanum.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function camelName($subject, string $case = '') : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z][a-zA-Z\d]*$/';
                break;
            case 'upper':
                $regex = '/^[A-Z][a-zA-Z\d]*$/';
                break;
            default:
                $regex = '/^[a-zA-Z][a-zA-Z\d]*$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * Snake cased name: starts with alpha, followed by alphanum/underscore.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function snakeName($subject, string $case = '') : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z][a-z\d_]*$/';
                break;
            case 'upper':
                $regex = '/^[A-Z][A-Z\d_]*$/';
                break;
            default:
                $regex = '/^[a-zA-Z][a-zA-Z\d_]*$/';
                break;
        }
        return !!preg_match($regex, $v);
    }

    /**
     * Lisp cased name: starts with alpha, followed by alphanum/dash.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function lispName($subject, string $case = '') : bool
    {
        $v = '' . $subject;
        if ($v === '') {
            return false;
        }
        switch ($case) {
            case 'lower':
                $regex = '/^[a-z][a-z\d\-]*$/';
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
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function uuid($subject, string $case = '') : bool
    {
        $v = '' . $subject;
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
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      False on empty.
     */
    public function base64($subject) : bool
    {
        return !!preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $subject);
    }

    /**
     * Ultimate catch-all ISO-8601 date/datetime timestamp.
     *
     * Positive timezone may be indicated by plus or space, because plus tends
     * to become space when URL decoding.
     *
     * YYYY-MM-DD([T ]HH(:ii(:ss)?(.m{1,N})?)?(Z|[+- ]HH:?(II)?)?)?
     * The format is supported by native \DateTime constructor.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateISO($subject, int $subSeconds = -1) : bool
    {
        $v = '' . $subject;
        if (strlen($v) == 10) {
            return !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
        }
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO_SUBSECONDS_MAX : $subSeconds;
            $m = '(\.\d{1,' . $ss . '})?';
        }
        return strlen($v) <= 26 + $ss
            && !!preg_match(
                '/^\d{4}\-\d{2}\-\d{2}([T ]\d{2}(:\d{2}(:\d{2}' . $m . ')?)?)?(Z|[ \+\-]\d{2}:?\d{0,2})?$/',
                $v
            );
    }

    /**
     * ISO-8601 date without timezone indication.
     *
     * YYYY-MM-DD
     * The format is supported by native \DateTime constructor.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function dateISOLocal($subject) : bool
    {
        $v = '' . $subject;
        return strlen($v) == 10 && !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
    }

    /**
     * ISO-8601 time timestamp without timezone indication.
     *
     * Doesn't require seconds. Allows no or some decimals of seconds.
     *
     * HH:ii(:ss)?(.m{1,N})?
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function timeISO($subject, int $subSeconds = -1) : bool
    {
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO_SUBSECONDS_MAX : $subSeconds;
            $m = '(\.\d{1,' . $ss . '})?';
        }
        return strlen($v) <= 9 + $ss
            && !!preg_match(
                '/^\d{2}:\d{2}(:\d{2}' . $m . ')?$/',
                $v
            );
    }

    /**
     * Catch-all ISO-8601 datetime timestamp with timezone indication.
     *
     * Doesn't require seconds. Allows no or some decimals of seconds.
     *
     * Positive timezone may be indicated by plus or space, because plus tends
     * to become space when URL decoding.
     *
     * YYYY-MM-DDTHH:ii(:ss)?(.m{1,N})?(Z|[+- ]HH:?(II)?)
     * The format is supported by native \DateTime constructor.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISO($subject, int $subSeconds = -1) : bool
    {
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO_SUBSECONDS_MAX : $subSeconds;
            $m = '(\.\d{1,' . $ss . '})?';
        }
        return strlen($v) <= 26 + $ss
            && !!preg_match(
                '/^\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}(:\d{2}' . $m . ')?(Z|[ \+\-]\d{2}:?\d{0,2})$/',
                $v
            );
    }

    /**
     * ISO-8601 datetime without timezone indication.
     *
     * Doesn't require seconds. Forbids decimals of seconds.
     *
     * YYYY-MM-DD HH:II(:SS)?
     * The format is supported by native \DateTime constructor.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function dateTimeISOLocal($subject) : bool
    {
        $v = '' . $subject;
        return strlen($v) <= 19 && !!preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v);
    }

    /**
     * ISO-8601 datetime timestamp with timezone marker.
     *
     * Doesn't require seconds. Allows no or some decimals of seconds.
     *
     * Positive timezone may be indicated by plus or space, because plus tends
     * to become space when URL decoding.
     *
     * YYYY-MM-DDTHH:ii(:ss)?(.m{1,N})?[+- ]HH(:II)?
     * The format is supported by native \DateTime constructor.
     *
     * @@see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISOZonal($subject, int $subSeconds = -1) : bool
    {
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO_SUBSECONDS_MAX : $subSeconds;
            $m = '(\.\d{1,' . $ss . '})?';
        }
        return strlen($v) <= 26 + $ss
            && !!preg_match(
                '/^\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}(:\d{2}' . $m . ')?[ \+\-]\d{2}(:\d{2})?$/',
                $v
            );
    }

    /**
     * ISO-8601 datetime timestamp UTC with Z marker.
     *
     * Doesn't require seconds. Allows no or some decimals of seconds.
     *
     * YYYY-MM-DDTHH:ii(:ss)?(.m{1,N})?Z
     * The format is supported by native \DateTime constructor.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISOUTC($subject, int $subSeconds = -1) : bool
    {
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO_SUBSECONDS_MAX : $subSeconds;
            $m = '(\.\d{1,' . $ss . '})?';
        }
        return strlen($v) <= 21 + $ss
            && !!preg_match(
                '/^\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}(:\d{2}' . $m . ')?Z$/',
                $v
            );
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * Doesn't contain tags.
     *
     * Beware: Returns true on empty ('') string.
     *
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function plainText($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? true : !strcmp($v, strip_tags($v));
    }

    /**
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function ipAddress($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_IP);
    }

    /**
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function url($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_URL);
    }

    /**
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function httpUrl($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? false : (
            strpos($v, 'http') === 0
            && !!filter_var('' . $v, FILTER_VALIDATE_URL)
        );
    }

    /**
     * @see TypeRulesTrait::stringable()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function email($subject) : bool
    {
        // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
        $v = '' . $subject;
        return $v === '' ? false : (
            !!filter_var($v, FILTER_VALIDATE_EMAIL)
            && !!preg_match('/\.[a-zA-Z\d]+$/', $v)
        );
    }
}
