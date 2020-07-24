<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * Rules that promise to check subject's type.
 *
 *
 * Some methods return string on pass
 * ----------------------------------
 * Composite type checkers like:
 * - number, stringable, numeric, container, loopable
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
     * But they are type-safe; intended to handled any kind of type gracefully.
     *
     * Not required by TypeRulesInterface, but somewhat affiliated.
     * @see TypeRulesInterface::MINIMAL_TYPE_RULES
     */

    /**
     * Subject is null, falsy or array|object is empty.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * ArrayAccess that is neither Countable nor Traversable fails validation,
     * because no means of accessing it's content.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function empty($subject) : bool
    {
        if (!$subject) {
            // Stringed zero - '0' - is not empty.
            return $subject !== '0';
        }
        if (is_object($subject)) {
            if ($subject instanceof \Countable) {
                return !count($subject);
            }
            if ($subject instanceof \Traversable) {
                // No need to check/use ArrayObject|ArrayIterator, because
                // those are both Countable (checked before this check).

                // Have to iterate; horrible.
                /** @noinspection PhpUnusedLocalVariableInspection */
                foreach ($subject as $ignore) {
                    return false;
                }
                return true;
            }
            if ($subject instanceof \ArrayAccess) {
                return false;
            }
            return !get_object_vars($subject);
        }
        return false;
    }

    /**
     * Subject is not falsy or array|object is non-empty.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * ArrayAccess that is neither Countable nor Traversable fails validation,
     * because no means of accessing it's content.
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function nonEmpty($subject) : bool
    {
        if (
            $subject instanceof \ArrayAccess
            && !($subject instanceof \Countable) && !($subject instanceof \Traversable)
        ) {
            return false;
        }
        return !$this->empty($subject);
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
    public function equatable($subject) : bool
    {
        return $subject === null || (is_scalar($subject) && !is_float($subject));
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
     *      bool|int to pass validation.
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
     *      int|float to pass validation.
     *
     * @return string|bool
     *      String (integer|float) on pass, boolean false on failure.
     */
    public function number($subject)
    {
        if (is_int($subject)) {
            return 'integer';
        }
        if (is_float($subject)) {
            return 'float';
        }
        return false;
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
        return $subject !== null
            && (is_string($subject) || is_int($subject) || is_float($subject));
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
        return is_string($subject) || (is_object($subject) && method_exists($subject, '__toString'));
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
     * @return string|bool
     *      String (string|integer|float|object) on pass,
     *      boolean false on validation failure.
     */
    public function stringable($subject)
    {
        if ($subject !== null) {
            if (is_string($subject)) {
                return 'string';
            }
            if (is_int($subject)) {
                return 'integer';
            }
            if (is_float($subject)) {
                return 'float';
            }
            if (is_object($subject) && method_exists($subject, '__toString')) {
                return 'object';
            }
        }
        return false;
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

    // Numbers or stringed numbers.---------------------------------------------

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
     * @return string|bool
     *      String (integer|float) on pass,
     *      boolean false on validation failure.
     */
    public function numeric($subject)
    {
        if (is_int($subject)) {
            return 'integer';
        }
        if (is_float($subject)) {
            return 'float';
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
            if ($negative && !static::RULE_FLAGS['DECIMAL_NEGATIVE_ZERO'] && !str_replace('0', '', $num)) {
                // Minus zero is unhealthy.
                return false;
            }
            return 'integer';
        }

        // Remove dot.
        $int = str_replace('.', '', $num);
        $w_int = strlen($int);
        if ($w_int
            && $w_int == $w_num - 1
            && ctype_digit($int)
        ) {
            if ($negative && !static::RULE_FLAGS['DECIMAL_NEGATIVE_ZERO'] && !str_replace('0', '', $int)) {
                // Minus zero is unhealthy.
                return false;
            }
            return 'float';
        }

        return false;
    }

    /**
     * Non-negative integer or stringed integer.
     *
     * If negative integer should pass, use numeric()
     * and then check it's return value.
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
            return $subject < 0 ? false : true;
        }
        if (!is_string($subject)) {
            return false;
        }
        // Yes, ctype_... returns fals on ''.
        return ctype_digit('' . $subject);
    }

    /**
     * Stringed number, optionally limiting number digits after dot.
     *
     * @param mixed $subject
     * @param int $maxDecimals
     *      -1: no limit.
     *
     * @return bool
     */
    public function decimal($subject, int $maxDecimals = -1) : bool
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
            if ($negative && !static::RULE_FLAGS['DECIMAL_NEGATIVE_ZERO'] && !str_replace('0', '', $num)) {
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
            if ($negative && !static::RULE_FLAGS['DECIMAL_NEGATIVE_ZERO'] && !str_replace('0', '', $int)) {
                // Minus zero is unhealthy.
                return false;
            }

            if ($maxDecimals > -1 && strlen(ltrim($num, '0123456789.')) > $maxDecimals) {
                // num.substr(num.indexOf('.') + 1).length;
                //$n = strlen( substr($num, strpos($num, '.') + 1) );
                //$n = strlen( ltrim($num, '012345679.') );
                return false;
            }

            // Float.
            return true;
        }

        return false;
    }


    // Containers.--------------------------------------------------------------

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
     *      object to pass validation.
     * @param string $className
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg className empty.
     */
    public function class($subject, string $className) : bool
    {
        if (!$className) {
            throw new InvalidArgumentException('Arg className is empty.');
        }
        return $subject && $subject instanceof $className;
    }

    /**
     *
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
     * Array or object.
     *
     * Superset of all other object and array type(ish) checkers; here:
     * - iterable, loopable, indexedIterable, keyedIterable, indexedLoopable,
     *   keyedLoopable, class, array, indexedArray, keyedArray
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function container($subject)
    {
        //return is_array($subject) ? 'array' : (
        //    $subject && is_object($subject) ? (
        //        $subject instanceof \Traversable ? (
        //            $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
        //        ) : 'object'
        //    ) : false
        //);

        if (is_array($subject)) {
            return 'array';
        }
        if ($subject && is_object($subject)) {
            if ($subject instanceof \Traversable) {
                return $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable';
            }
            return 'object';
        }
        return false;
    }

    /**
     * Array or Traversable object.
     *
     * Not very useful because stdClass _is_ iterable.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @see loopable()
     * @see indexedIterable()
     * @see keyedIterable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function iterable($subject)
    {
        //return is_array($subject) ? 'array' : (
        //    $subject && $subject instanceof \Traversable ? (
        //        $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
        //    ) : false
        //);

        if (is_array($subject)) {
            return 'array';
        }
        if ($subject && $subject instanceof \Traversable) {
            return $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable';
        }
        return false;
    }

    /**
     * Array or Traversable object, or non-Traversable non-ArrayAccess object.
     *
     * Cannot promise that an object is iterable, but at least rules out
     * non-Traversable ArrayAccess.
     *
     * 'arrayAccess' is here a Traversable ArrayAccess object.
     *
     * Counter to iterable loopable allows non-Traversable object,
     * except if (also) ArrayAccess.
     *
     * Non-Traversable ArrayAccess is (hopefully) the only relevant container
     * class/interface that isn't iterable.
     *
     * @see container()
     * @see iterable()
     * @see indexedLoopable()
     * @see keyedLoopable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function loopable($subject)
    {
        // Only difference vs container() is that non-Traversable ArrayAccess
        // doesn't pass here.

        //return is_array($subject) ? 'array' : (
        //    $subject && is_object($subject) ? (
        //        $subject instanceof \Traversable ? (
        //            $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
        //        ) : (
        //            $subject instanceof \ArrayAccess ? false : 'object'
        //        )
        //    ) : false
        //);

        if (is_array($subject)) {
            return 'array';
        }
        if ($subject && is_object($subject)) {
            if ($subject instanceof \Traversable) {
                return $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable';
            }
            return $subject instanceof \ArrayAccess ? false : 'object';
        }
        return false;
    }

    /**
     * Empty or indexed iterable.
     *
     * @see iterable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function indexedIterable($subject)
    {
        return static::indexedOrKeyedContainer($subject, false, false);
    }

    /**
     * Empty or keyed iterable.
     *
     * @see iterable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function keyedIterable($subject)
    {
        return static::indexedOrKeyedContainer($subject, false, true);
    }

    /**
     * Empty or indexed loopable.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function indexedLoopable($subject)
    {
        return static::indexedOrKeyedContainer($subject, true, false);
    }

    /**
     * Empty or keyed loopable.
     *
     * @see loopable()
     *
     * @param mixed $subject
     *      object|array to pass validation.
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function keyedLoopable($subject)
    {
        return static::indexedOrKeyedContainer($subject, true, true);
    }

    /**
     * Empty array or numerically indexed array.
     *
     * Does not check if the array's index is complete and correctly sequenced.
     *
     * @see TypeRulesTrait::array()
     *
     * @param mixed $subject
     *
     * @return bool
     *      True: empty array, or all keys are integers.
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
     * Empty array or keyed array.
     *
     * @see TypeRulesTrait::array()
     *
     * @param mixed $subject
     *
     * @return bool
     *      True: empty array, or at least one key is not integer.
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

    
    // Helpers.-----------------------------------------------------------------

    /**
     * @see indexedIterable()
     * @see indexedLoopable()
     * @see keyedIterable()
     * @see keyedLoopable()
     *
     * @param mixed $subject
     * @param bool $loopable
     * @param bool $keyed
     *
     * @return string|bool
     *      String on pass, false on failure.
     *
     * @throws InvalidArgumentException
     *      Logical error, arg kind not supported.
     */
    protected static function indexedOrKeyedContainer($subject, bool $loopable, bool $keyed)
    {
        if (is_array($subject)) {
            if (!$subject) {
                return 'array';
            }
            return ctype_digit(join('', array_keys($subject))) ?
                (!$keyed ? 'array' : false) : ($keyed ? 'array' : false);
        }
        if ($subject && is_object($subject)) {
            if ($subject instanceof \Traversable) {
                if ($subject instanceof \Countable && !count($subject)) {
                    return $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable';
                }
                if ($subject instanceof \ArrayObject || $subject instanceof \ArrayIterator) {
                    $keys = array_keys($subject->getArrayCopy());
                    if (!$keys) {
                        return 'arrayAccess';
                    }
                    return ctype_digit(join('', $keys)) ?
                        (!$keyed ? 'arrayAccess' : false) : ($keyed ? 'arrayAccess' : false);
                }
                else {
                    // Have to iterate; horrible.
                    $keys = [];
                    foreach ($subject as $k => $ignore) {
                        $keys[] = $k;
                    }
                    if (!$keys) {
                        return 'traversable';
                    }
                    return ctype_digit(join('', $keys)) ?
                        (!$keyed ? 'traversable' : false) : ($keyed ? 'traversable' : false);
                }
            }
            elseif ($loopable) {
                if (!($subject instanceof \ArrayAccess) && $subject instanceof \Countable && !count($subject)) {
                    return 'object';
                }
                $keys = array_keys(get_object_vars($subject));
                if (!$keys) {
                    return 'object';
                }
                return ctype_digit(join('', $keys)) ?
                    (!$keyed ? 'object' : false) : ($keyed ? 'object' : false);
            }
        }
        return false;
    }
}
