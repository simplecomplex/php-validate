<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * Rules that promise to check the subject's type.
 *
 *
 * Design technicalities
 * ---------------------
 * Equivalent interface:
 * @see \SimpleComplex\Validate\Interfaces\TypeRulesInterface
 *
 * @package SimpleComplex\Validate
 */
trait TypeRulesTrait
{
    // Type indifferent, but type safe.-----------------------------------------

    /**
     * empty() and nonEmpty() are not usable as type-checking condition before
     * other rules.
     * But they are type-safe; intended to handle any kind of type gracefully.
     */

    /**
     * Subject is falsy, or array or 'sizeable' object is empty.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $subject
     *
     * @return bool
     *      False: is not empty, or is non-countable object.
     */
    public function empty($subject) : bool
    {
        if (!$subject) {
            // Passes null|scalar|array.
            // Stringed zero - '0' - is not empty.
            return $subject !== '0';
        }
        if (is_object($subject)) {
            if ($subject instanceof \stdClass) {
                return !get_object_vars($subject);
            }
            if ($subject instanceof \Countable) {
                return !count($subject);
            }
            if ($subject instanceof \Traversable) {
                // Have to iterate; horrible.
                foreach ($subject as $ignore) {
                    return false;
                }
                return true;
            }
            // Non-countable object
            return false;
        }
        // Non-empty scalar|array, or resource.
        return false;
    }

    /**
     * Subject is not falsy, or array or 'sizeable' object is not empty.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $subject
     *
     * @return bool
     *      False: is empty, or is non-countable object.
     */
    public function nonEmpty($subject) : bool
    {
        if (!$subject) {
            // Fails null|scalar|array.
            // Stringed zero - '0' - is not empty.
            return $subject === '0';
        }
        if (is_object($subject)) {
            if ($subject instanceof \stdClass) {
                return !!get_object_vars($subject);
            }
            if ($subject instanceof \Countable) {
                return !!count($subject);
            }
            if ($subject instanceof \Traversable) {
                // Have to iterate; horrible.
                foreach ($subject as $ignore) {
                    return true;
                }
                return false;
            }
            // Non-countable object
            return false;
        }
        // Non-empty scalar|array, or resource.
        return true;
    }


    // Scalar/null.-------------------------------------------------------------

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function null($subject) : bool
    {
        return $subject === null;
    }

    /**
     * Boolean, integer, float, string or null.
     *
     * Needed if someone defines an enum() which can compare floats.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function scalarNull($subject) : bool
    {
        return $subject === null || is_scalar($subject);
    }

    /**
     * Boolean, integer, float or string.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function scalar($subject) : bool
    {
        return is_scalar($subject);
    }

    /**
     * Boolean, integer, string or null.
     *
     * Float is not equatable.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function equatableNull($subject) : bool
    {
        return $subject === null || (is_scalar($subject) && !is_float($subject));
    }

    /**
     * Boolean, integer, string.
     *
     * Float is not equatable.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function equatable($subject) : bool
    {
        return $subject !== null && (is_scalar($subject) && !is_float($subject));
    }

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function boolean($subject) : bool
    {
        return is_bool($subject);
    }

    /**
     * Boolean or integer 0|1.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit($subject) : bool
    {
        if (is_bool($subject)) {
            return true;
        }
        if (is_int($subject)) {
            return $subject == 0 || $subject == 1;
        }
        return false;
    }

    /**
     * Integer or float.
     *
     * @see numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function number($subject) : bool
    {
        return is_int($subject) || is_float($subject);
    }

    /**
     * @see digital()
     *
     * @see range()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function integer($subject) : bool
    {
        return is_int($subject);
    }

    /**
     * @see numeric()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function float($subject) : bool
    {
        return is_float($subject);
    }


    // Number or stringed number.-----------------------------------------------

    /**
     * Integer, float or stringed integer/float.
     *
     * Unlike native is_numeric() this method doesn't allow
     * leading plus, leading space nor e-notation.
     *
     * @see number()
     *
     * @param mixed $subject
     *      Checked stringified.
     *      Stringable object tests false; method promises type safety.
     *      int|float|string to pass validation.
     *
     * @return bool
     */
    public function numeric($subject) : bool
    {
        if (is_int($subject) || is_float($subject)) {
            return true;
        }

        /**
         * Same algo as decimal(), but this returns string on pass.
         * @see decimal()
         */
        if (!is_string($subject)) {
            return false;
        }
        $w = strlen($subject);
        if (!$w) {
            return false;
        }

        // Remove minus.
        $num = ltrim($subject, '-');
        $w_num = strlen($num);
        if (!$w_num || $w_num < $w - 1) {
            return false;
        }
        $negative = $w_num == $w - 1;

        if (ctype_digit($num)) {
            if ($negative && !static::RULE_FLAGS['STRINGED_NEGATIVE_ZERO'] && !str_replace('0', '', $num)) {
                // Minus zero is unhealthy.
                return false;
            }
            return true;
        }

        // Remove dot.
        $int = str_replace('.', '', $num);
        $w_int = strlen($int);
        if ($w_int
            && $w_int == $w_num - 1
            && ctype_digit($int)
        ) {
            if ($negative && !static::RULE_FLAGS['STRINGED_NEGATIVE_ZERO'] && !str_replace('0', '', $int)) {
                // Minus zero is unhealthy.
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Integer or stringed integer.
     *
     * @see integer()
     *
     * @param mixed $subject
     *      Checked stringified.
     *      Stringable object tests false; method promises type safety.
     *      int|string to pass validation.
     *
     * @return bool
     */
    public function digital($subject) : bool
    {
        if (is_int($subject)) {
            return true;
        }
        if (!is_string($subject)) {
            return false;
        }
        $w = strlen($subject);
        if (!$w) {
            return false;
        }
        $int = ltrim($subject, '-');
        $w_int = strlen($int);
        if (!$w_int || $w_int < $w - 1) {
            return false;
        }
        $negative = $w_int == $w - 1;

        if (ctype_digit($int)) {
            if ($negative && !static::RULE_FLAGS['STRINGED_NEGATIVE_ZERO'] && !str_replace('0', '', $int)) {
                // Minus zero is unhealthy.
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Stringed number.
     *
     * Decimal point is not a requirement;
     * a stringed integer is also stringed number.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function decimal($subject) : bool
    {
        /**
         * Same algo as numeric(), but this returns boolean on pass.
         * @see numeric()
         */
        if (!is_string($subject)) {
            return false;
        }
        $w = strlen($subject);
        if (!$w) {
            return false;
        }

        // Remove minus.
        $num = ltrim($subject, '-');
        $w_num = strlen($num);
        if (!$w_num || $w_num < $w - 1) {
            return false;
        }
        $negative = $w_num == $w - 1;

        if (ctype_digit($num)) {
            if ($negative && !static::RULE_FLAGS['STRINGED_NEGATIVE_ZERO'] && !str_replace('0', '', $num)) {
                // Minus zero is unhealthy.
                return false;
            }
            // Integer.
            return true;
        }

        // Remove dot.
        $int = str_replace('.', '', $num);
        $w_int = strlen($int);
        if ($w_int
            && $w_int == $w_num - 1
            && ctype_digit($int)
        ) {
            if ($negative && !static::RULE_FLAGS['STRINGED_NEGATIVE_ZERO'] && !str_replace('0', '', $int)) {
                // Minus zero is unhealthy.
                return false;
            }
            // Float.
            return true;
        }

        return false;
    }


    // String/stringable.-------------------------------------------------------

    /**
     * Alternatives:
     * @see stringableScalar()
     * @see stringableObject()
     * @see stringStringableObject()
     * @see stringable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function string($subject) : bool
    {
        return is_string($subject);
    }

    /**
     * String or number.
     *
     * Alternatives:
     * @see string()
     * @see stringableObject()
     * @see stringStringableObject()
     * @see stringable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function stringableScalar($subject) : bool
    {
        return is_string($subject)
            || is_int($subject)
            || is_float($subject);
    }

    /**
     * Stringable object, not string.
     *
     * Alternatives:
     * @see string()
     * @see stringableScalar()
     * @see stringStringableObject()
     * @see stringable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function stringableObject($subject) : bool
    {
        return is_object($subject) && method_exists($subject, '__toString');
    }

    /**
     * String or stringable object.
     *
     * Alternatives:
     * @see string()
     * @see stringableScalar()
     * @see stringableObject()
     * @see stringable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function stringStringableObject($subject) : bool
    {
        return is_string($subject)
            || (is_object($subject) && method_exists($subject, '__toString'));
    }

    /**
     * String, number or stringable object.
     *
     * @see string()
     * @see stringableScalar()
     * @see stringableObject()
     * @see stringStringableObject()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function stringable($subject) : bool
    {
        return is_string($subject)
            || is_int($subject)
            || is_float($subject)
            || (is_object($subject) && method_exists($subject, '__toString'));
    }


    // Container.---------------------------------------------------------------

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function object($subject) : bool
    {
        return is_object($subject);
    }

    /**
     * Is object and is of that class or interface, or has it as ancestor.
     *
     * @param mixed $subject
     * @param string $className
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg $className empty.
     */
    public function class($subject, string $className) : bool
    {
        if (!$className) {
            throw new InvalidArgumentException('Arg $className is empty.');
        }
        return $subject instanceof $className;
    }

    /**
     * Is \stdClass.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function stdClass($subject) : bool
    {
        return $subject instanceof \stdClass;
    }

    /**
     * Is extending class; object but not \stdClass.
     *
     * @param $subject
     *
     * @return bool
     */
    public function extClass($subject) : bool
    {
        return is_object($subject) && get_class($subject) != \stdClass::class;
    }

    /**
     * Anonymous class.
     *
     * Is in effect extending class (object not \stdClass) with bad class name.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function anonymousClass($subject) : bool
    {
        return is_object($subject) && substr(get_class($subject), 0, 15) === 'class@anonymous';
    }

    /**
     * @see indexedArray()
     * @see keyedArray()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function array($subject) : bool
    {
        return is_array($subject);
    }

    /**
     * Object or array.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function container($subject) : bool
    {
        return is_array($subject)
            || is_object($subject);
    }

    /**
     * Array or \Traversable object.
     *
     * Not very useful because \stdClass _is_ iterable.
     *
     * @see loopable()
     * @see indexedIterable()
     * @see keyedIterable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function iterable($subject) : bool
    {
        return is_iterable($subject);
    }

    /**
     * Array, \stdClass or \Traversable object.
     *
     * \stdClass is iterable for sure.
     * Whereas a non-\Traversable extending class cannot be determined
     * as iterable or not.
     *
     * @see iterable()
     * @see indexedLoopable()
     * @see keyedLoopable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function loopable($subject) : bool
    {
        return is_array($subject)
            || $subject instanceof \stdClass
            || $subject instanceof \Traversable;
    }

    /**
     * Array or \Countable object.
     *
     * @see sizeable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function countable($subject) : bool
    {
        return is_array($subject)
            || $subject instanceof \Countable;
    }

    /**
     * Array, \stdClass, \Countable or \Traversable.
     *
     * \stdClass cannot be count()'ed directly, but via
     * count(get_object_vars($obj)).
     *
     * Non-\Countable \Traversable can only be counted via iteration.
     *
     * @see loopable()
     * @see countable()
     * @see minSize()
     * @see maxSize()
     * @see exactSize()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function sizeable($subject) : bool
    {
        return is_array($subject)
            || $subject instanceof \stdClass
            || $subject instanceof \Countable
            || $subject instanceof \Traversable;
    }


    // Pattern-like container rules that have to check type to work.------------

    /**
     * Minimum size of array or \stdClass|\Countable|\Traversable object.
     *
     * @param mixed $subject
     * @param int $min
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg $min not non-negative.
     */
    public function minSize($subject, int $min) : bool
    {
        if ($min < 0) {
            throw new InvalidArgumentException('Arg $min[' . $min . '] is not non-negative.');
        }

        if (is_array($subject) || $subject instanceof \Countable) {
            return count($subject) >= $min;
        }
        if ($subject instanceof \stdClass) {
            return count(get_object_vars($subject)) >= $min;
        }
        if ($subject instanceof \Traversable) {
            $w = 0;
            // Have to iterate; horrible.
            foreach ($subject as $ignore) {
                ++$w;
            }
            return $w >= $min;
        }

        return false;
    }

    /**
     * Maximum size of array or \stdClass|\Countable|\Traversable object.
     *
     * @param mixed $subject
     * @param int $max
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg $max not non-negative.
     */
    public function maxSize($subject, int $max) : bool
    {
        if ($max < 0) {
            throw new InvalidArgumentException('Arg $max[' . $max . '] is not non-negative.');
        }

        if (is_array($subject) || $subject instanceof \Countable) {
            return count($subject) <= $max;
        }
        if ($subject instanceof \stdClass) {
            return count(get_object_vars($subject)) <= $max;
        }
        if ($subject instanceof \Traversable) {
            $w = 0;
            // Have to iterate; horrible.
            foreach ($subject as $ignore) {
                ++$w;
            }
            return $w <= $max;
        }

        return false;
    }

    /**
     * Exact size of array or \stdClass|\Countable|\Traversable object.
     *
     * @param mixed $subject
     * @param int $exact
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg $exact not non-negative.
     */
    public function exactSize($subject, int $exact) : bool
    {
        if ($exact < 0) {
            throw new InvalidArgumentException('Arg $exact[' . $exact . '] is not non-negative.');
        }

        if (is_array($subject) || $subject instanceof \Countable) {
            return count($subject) == $exact;
        }
        if ($subject instanceof \stdClass) {
            return count(get_object_vars($subject)) == $exact;
        }
        if ($subject instanceof \Traversable) {
            $w = 0;
            // Have to iterate; horrible.
            foreach ($subject as $ignore) {
                ++$w;
            }
            return $w == $exact;
        }

        return false;
    }

    /**
     * Empty or indexed array|\Traversable.
     *
     * @see iterable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function indexedIterable($subject) : bool
    {
        return static::indexedOrKeyedIterable($subject);
    }


    /**
     * Empty or keyed array|\Traversable.
     *
     * @see iterable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function keyedIterable($subject) : bool
    {
        return static::indexedOrKeyedIterable($subject, true);
    }

    /**
     * Empty or indexed array|\stdClass|\Traversable.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function indexedLoopable($subject) : bool
    {
        if ($subject instanceof \stdClass) {
            $keys = array_keys(get_object_vars($subject));
            return !$keys || ctype_digit(join('', $keys));
        }
        return static::indexedOrKeyedIterable($subject);
    }

    /**
     * Empty or keyed array|\stdClass|\Traversable.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function keyedLoopable($subject) : bool
    {
        if ($subject instanceof \stdClass) {
            $keys = array_keys(get_object_vars($subject));
            return !$keys || !ctype_digit(join('', $keys));
        }
        return static::indexedOrKeyedIterable($subject, true);
    }

    /**
     * Empty or numerically indexed array.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function indexedArray($subject) : bool
    {
        if (!is_array($subject)) {
            return false;
        }
        if (!$subject) {
            return true;
        }
        return ctype_digit(join('', array_keys($subject)));
    }

    /**
     * Empty or keyed array.
     *
     * @param mixed $subject
     *
     * @return bool
     *      True: at least one key contains non-digit character.
     */
    public function keyedArray($subject) : bool
    {
        if (!is_array($subject)) {
            return false;
        }
        if (!$subject) {
            return true;
        }
        return !ctype_digit(join('', array_keys($subject)));
    }


    // Odd types.---------------------------------------------------------------

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function resource($subject) : bool
    {
        return is_resource($subject);
    }

    
    // Helpers.-----------------------------------------------------------------

    /**
     * @see indexedIterable()
     * @see keyedIterable()
     * @see indexedLoopable()
     * @see keyedLoopable()
     *
     * @param mixed $subject
     * @param bool $keyed
     *
     * @return bool
     */
    protected static function indexedOrKeyedIterable($subject, bool $keyed = false) : bool
    {
        if (is_array($subject)) {
            if (!$subject) {
                // Empty is always true.
                return true;
            }
            return ctype_digit(join('', array_keys($subject))) ? (!$keyed) : $keyed;
        }
        if ($subject instanceof \Traversable) {
            if ($subject instanceof \Countable && !count($subject)) {
                // Empty is always true.
                return true;
            }
            if ($subject instanceof \ArrayObject || $subject instanceof \ArrayIterator) {
                $keys = array_keys($subject->getArrayCopy());
            }
            else {
                $keys = [];
                // Have to iterate; horrible.
                foreach ($subject as $k => $ignore) {
                    $keys[] = $k;
                }
            }
            if (!$keys) {
                // Empty is always true.
                return true;
            }
            return ctype_digit(join('', $keys)) ? (!$keyed) : $keyed;
        }
        return false;
    }
}
