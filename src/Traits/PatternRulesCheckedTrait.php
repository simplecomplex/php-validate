<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Traits;

use SimpleComplex\Validate\Helper;
use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * Pattern rules - type-checking implementation.
 *
 *
 * @mixin \SimpleComplex\Validate\ValidateUnchecked
 *
 * @package SimpleComplex\Validate
 */
trait PatternRulesCheckedTrait
{
    /**
     * Subject strictly equal to a bucket of an array.
     *
     * Checks whether all allowed values are bool|int|string|null.
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
     *
     * @throws InvalidArgumentException
     *      Arg allowedValues is empty.
     *      A bucket of arg allowedValues is not bool|int|string|null.
     */
    public function enum($subject, array $allowedValues) : bool
    {
        if (!$allowedValues) {
            throw new InvalidArgumentException('Arg allowedValues is empty.');
        }
        if ($subject !== null && (is_float($subject) || !is_scalar($subject))) {
            return false;
        }
        $i = -1;
        foreach ($allowedValues as $allowed) {
            ++$i;
            if ($allowed !== null && (is_float($allowed) || !is_scalar($allowed))) {
                throw new InvalidArgumentException(
                    'Arg allowedValues bucket ' . $i . ' type[' . Helper::getType($allowed)
                    . '] is not bool|int|string|null.'
                );
            }
            if ($subject === $allowed) {
                return true;
            }
        }
        return false;
    }


    // Numeric secondaries.-----------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function bit32($subject) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::bit32($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function bit64($subject) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::bit64($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function positive($subject) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::positive($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function nonNegative($subject) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::nonNegative($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function negative($subject) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::negative($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function min($subject, $min) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::min($subject, $min);
    }

    /**
     * {@inheritDoc}
     */
    public function max($subject, $max) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::max($subject, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function range($subject, $min, $max) : bool
    {
        if (!$this->numeric($subject)) {
            return false;
        }
        return parent::range($subject, $min, $max);
    }


    // String character set indifferent.----------------------------------------

    /**
     * {@inheritDoc}
     */
    public function regex($subject, string $pattern) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::regex($subject, $pattern);
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function unicode($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicode($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function unicodePrintable($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicodePrintable($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function unicodeMultiLine($subject, $noCarriageReturn = false) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicodeMultiLine($subject, $noCarriageReturn);
    }

    /**
     * {@inheritDoc}
     */
    public function unicodeMinLength($subject, int $min) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicodeMinLength($subject, $min);
    }

    /**
     * {@inheritDoc}
     */
    public function unicodeMaxLength($subject, int $max) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicodeMaxLength($subject, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function unicodeExactLength($subject, int $exact) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::unicodeExactLength($subject, $exact);
    }


    // ASCII string secondaries.------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function hex($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::hex($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function ascii($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::ascii($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function asciiPrintable($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::asciiPrintable($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function asciiMultiLine($subject, $noCarriageReturn = false) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::asciiMultiLine($subject, $noCarriageReturn);
    }

    /**
     * {@inheritDoc}
     */
    public function minLength($subject, int $min) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::minLength($subject, $min);
    }

    /**
     * {@inheritDoc}
     */
    public function maxLength($subject, int $max) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::maxLength($subject, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function exactLength($subject, int $exact) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::exactLength($subject, $exact);
    }


    // ASCII specials.----------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function alphaNum($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::alphaNum($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function name($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::name($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function camelName($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::camelName($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function snakeName($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::snakeName($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function lispName($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::lispName($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function uuid($subject, string $case = '') : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::uuid($subject, $case);
    }

    /**
     * {@inheritDoc}
     */
    public function base64($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::base64($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function dateISO8601($subject, int $subSeconds = -1) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateISO8601($subject, $subSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function dateISO8601Local($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateISO8601Local($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function timeISO8601($subject, int $subSeconds = -1) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::timeISO8601($subject, $subSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function dateTimeISO8601($subject, int $subSeconds = -1) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateTimeISO8601($subject, $subSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function dateTimeISO8601Local($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateTimeISO8601Local($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function dateTimeISO8601Zonal($subject, int $subSeconds = -1) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateTimeISO8601Zonal($subject, $subSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function dateTimeISOUTC($subject, int $subSeconds = -1) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::dateTimeISOUTC($subject, $subSeconds);
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * {@inheritDoc}
     */
    public function plainText($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::plainText($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function ipAddress($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::ipAddress($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function url($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::url($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function httpUrl($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::httpUrl($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function email($subject) : bool
    {
        if (!$this->stringable($subject)) {
            return false;
        }
        return parent::email($subject);
    }
}
