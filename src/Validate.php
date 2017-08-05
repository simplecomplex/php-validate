<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Unicode;
use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\BadMethodCallException;

// @todo: rename all $var parameters to $subject.

/**
 * Validate almost anything.
 *
 * Some string methods return true on empty
 * ----------------------------------------
 * Combine with the 'nonEmpty' rule if requiring non-empty.
 * They are:
 * - unicode, unicodePrintable, unicodeMultiLine
 * - ascii, asciiPrintable, asciiMultiLine
 * - plainText
 *
 * Some methods return string on pass
 * ----------------------------------
 * Composite type checkers like:
 * - numeric, container, iterable, indexedIterable, keyedIterable
 *
 * Maximum number of rule method parameters
 * ----------------------------------------
 * A rule method is not allowed to have more than 5 parameters,
 * that is: 1 for the var to validate and max. 4 secondary
 * (specifying) parameters.
 * ValidateByRules::challenge() will err when given more than 4 secondary args.
 *
 * Rule methods invalid arg checks
 * -------------------------------
 * Rule methods that take more arguments than the $var to validate
 * must check those arguments for type/emptyness and throw exception
 * on such error.
 *
 * @dependency-injection-container validator
 *      Suggested ID of a global Validate instance.
 *
 * @package SimpleComplex\Validate
 */
class Validate implements RuleProviderInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var Validate
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * Consider using a dependency injection container instead.
     *
     * @see \SimpleComplex\Utils\Dependency
     * @see \Slim\Container
     *
     * @param mixed ...$constructorParams
     *      Validate child class constructor may have parameters.
     *
     * @return Validate
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            // Validate child class constructor may have parameters.
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * Methods of this class that a ValidateByRules instance should never call.
     *
     * Does not contain __construct nor __call; would be slightly paranoid.
     *
     * @var array
     */
    const NON_RULE_METHODS = [
        'getInstance',
        'flushInstance',
        '__construct',
        'make',
        'getNonRuleMethods',
        '__call',
        'challenge',
        'challengeRecording',
    ];

    /**
     * @var Unicode
     */
    protected $unicode;

    /**
     * @var array
     */
    protected $nonRuleMethods = [];

    /**
     * @see Validate::getInstance()
     */
    public function __construct()
    {
        // Dependencies.--------------------------------------------------------
        // Extending class' constructor might provide instance by other means.
        if (!$this->unicode) {
            $this->unicode = Unicode::getInstance();
        }

        // Business.------------------------------------------------------------
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
     * @return array
     */
    public function getNonRuleMethods() : array
    {
        if (!$this->nonRuleMethods) {
            $this->nonRuleMethods = self::NON_RULE_METHODS;
        }
        return $this->nonRuleMethods;
    }

    /**
     * By design, ValidateByRules::challenge() should not be able to call
     * a non-existent method of this class.
     * But external call to Validate::noSuchRule() is somewhat expectable.
     *
     * @see ValidateByRules::challenge()
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws BadMethodCallException
     *      Undefined rule method by arg name.
     */
    public function __call($name, $arguments)
    {
        throw new BadMethodCallException('Undefined validation rule[' . $name . '].');
    }


    // Validate by list of rules.---------------------------------------------------------------------------------------

    /**
     * Validate by a list of rules.
     *
     * Reuses the same ValidateByRules instance on every call.
     * Instance saved on ValidateByRules class, not here.
     *
     * @param mixed $var
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return bool
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($var, $ruleSet) : bool
    {
        // Re-uses instance on ValidateByRules rules.
        // Since we pass this object to the ValidateByRules instance,
        // we shan't refer the ValidateByRules instance directly.
        return ValidateByRules::getInstance(
            $this
        )->challenge($var, $ruleSet);
    }

    /**
     * Validate by a list of rules, recording validation failures.
     *
     * Creates a new ValidateByRules instance on every call.
     *
     * @code
     * $good_bike = Validate::make()->challengeRecording($bike, $rules);
     * if (empty($good_bike['passed'])) {
     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
     * }
     * @endcode
     *
     * @param mixed $var
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challengeRecording($var, $ruleSet)
    {
        $validate_by_rules = new ValidateByRules($this, [
            'recordFailure' => true,
        ]);

        $validate_by_rules->challenge($var, $ruleSet);
        $record = $validate_by_rules->getRecord();

        return [
            'passed' => !$record,
            'record' => $record,
        ];
    }


    // Rules.-----------------------------------------------------------------------------------------------------------

    // Type indifferent.--------------------------------------------------------

    /**
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function empty($var) : bool
    {
        if (!$var) {
            // Stringed zero - '0' - is not empty.
            return $var !== '0';
        }
        if ($var instanceof \ArrayAccess) {
            return !((array) $var);
        }
        if (is_object($var)) {
            return !get_object_vars($var);
        }
        return false;
    }

    /**
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonEmpty($var) : bool
    {
        return !$this->empty($var);
    }

    /**
     * Compares type strict, and allowed values must be scalar or null.
     *
     * @param mixed $var
     * @param array $allowedValues
     *      [
     *          0: some scalar
     *          1: null
     *          3: other scalar
     *      ]
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg allowedValues is empty.
     *      A bucket of arg allowedValues is no scalar or null.
     */
    public function enum($var, array $allowedValues) : bool
    {
        if (!$allowedValues) {
            throw new InvalidArgumentException('Arg allowedValues is empty.');
        }
        $i = -1;
        foreach ($allowedValues as $allowed) {
            ++$i;
            if ($allowed !== null && !is_scalar($allowed)) {
                throw new InvalidArgumentException(
                    'Arg allowedValues bucket ' . $i . ' type['
                    . (!is_object($allowed) ? gettype($allowed) : get_class($allowed)) . '] is not scalar or null.'
                );
            }
            if ($var === $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     * @param string $pattern
     *      /regular expression/
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg pattern empty
     */
    public function regex($var, string $pattern) : bool
    {
        if (!$pattern) {
            throw new InvalidArgumentException('Arg pattern is empty.');
        }
        return !!preg_match($pattern, '' . $var);
    }

    // Type checkers.-----------------------------------------------------------

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function boolean($var) : bool
    {
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
     * @return string|bool
     *      String (integer|float) on pass, boolean false on failure.
     */
    public function number($var)
    {
        if (is_int($var)) {
            return 'integer';
        }
        if (is_float($var)) {
            return 'float';
        }
        return false;
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
    public function integer($var) : bool
    {
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
    public function float($var) : bool
    {
        return is_float($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function string($var) : bool
    {
        return is_string($var);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function null($var) : bool
    {
        return $var === null;
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function resource($var) : bool
    {
        return is_resource($var);
    }

    /**
     * Object or array.
     *
     * Superset of all other object and array type(ish) checkers; here:
     * - iterable, indexedIterable, keyedIterable, class,
     *   array, indexedArray, keyedArray
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function container($var)
    {
        return is_array($var) ? 'array' : (
            $var && is_object($var) ? (
                $var instanceof \Traversable ? (
                    $var instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
                ) : 'object'
            ) : false
        );
    }

    /**
     * Iterable object or array.
     *
     * @param $var
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function iterable($var)
    {
        return is_array($var) ? 'array' : (
            $var && $var instanceof \Traversable ? (
                $var instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
            ) : false
        );
    }

    /**
     * Empty or indexed array, or empty or indexed ArrayAccess object.
     *
     * An ArrayAccess object which is neither \Countable, nor \ArrayObject
     * or \ArrayIterator, will fail this validation.
     *
     * @param $var
     *
     * @return string|bool
     *      String (array|arrayAccess) on pass,
     *      boolean false on validation failure.
     */
    public function indexedIterable($var)
    {
        if (is_array($var)) {
            if (!$var || ctype_digit(join('', array_keys($var)))) {
                return 'array';
            }
            return false;
        }
        if ($var && $var instanceof \ArrayAccess) {
            if ($var instanceof \Countable && !count($var)) {
                return 'arrayAccess';
            }
            if (
                ($var instanceof \ArrayObject || $var instanceof \ArrayIterator)
                && ctype_digit(join('', array_keys($var->getArrayCopy())))
            ) {
                return 'arrayAccess';
            }
            // An ArrayAccess object which is neither \Countable, nor
            // \ArrayObject or \ArrayIterator, must fail because we can't
            // access it's index/keys en bloc (only via foreach).
        }
        return false;
    }

    /**
     * Empty or keyed array, or empty or keyed ArrayAccess object,
     * or non-ArrayAccess Traversable.
     *
     * An ArrayAccess object which is neither \Countable, nor \ArrayObject
     * or \ArrayIterator, will fail this validation.
     *
     * @param $var
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function keyedIterable($var)
    {
        if (is_array($var)) {
            if (!$var || !ctype_digit(join('', array_keys($var)))) {
                return 'array';
            }
            return false;
        }
        if ($var && $var instanceof \Traversable) {
            if ($var instanceof \ArrayAccess) {
                if ($var instanceof \Countable && !count($var)) {
                    return 'arrayAccess';
                }
                if (
                    ($var instanceof \ArrayObject || $var instanceof \ArrayIterator)
                    && !ctype_digit(join('', array_keys($var->getArrayCopy())))
                ) {
                    return 'arrayAccess';
                }
                // An ArrayAccess object which is neither \Countable, nor
                // \ArrayObject or \ArrayIterator, must fail because we can't
                // access it's index/keys en bloc (only via foreach).
            } else {
                return 'traversable';
            }
        }
        return false;
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function object($var) : bool
    {
        return $var && is_object($var);
    }

    /**
     * Is object and is of that class or has it as ancestor.
     *
     * @uses is_a()
     *
     * @param mixed $var
     * @param string $className
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg className empty.
     */
    public function class($var, string $className) : bool
    {
        if (!$className) {
            throw new InvalidArgumentException('Arg className is empty.');
        }
        return $var && is_object($var) && is_a($var, $className);
    }

    /**
     * @param mixed $var
     *
     * @return bool
     */
    public function array($var) : bool
    {
        return is_array($var);
    }

    /**
     * Empty array or numerically indexed array.
     *
     * Does not check if the array's index is complete and correctly sequenced.
     *
     * @param mixed $var
     *
     * @return bool
     *      True: empty array, or all keys are integers.
     */
    public function indexedArray($var) : bool
    {
        if (!is_array($var)) {
            return false;
        }
        if (!$var) {
            return true;
        }
        return ctype_digit(join('', array_keys($var)));
    }

    /**
     * Empty array or keyed array.
     *
     * @param mixed $var
     *
     * @return bool
     *      True: empty array, or at least one key is not integer.
     */
    public function keyedArray($var) : bool
    {
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
     * Integer, float or stringed integer/float (but not e-notation).
     *
     * @code
     * $numeric = $validate->numeric($weakly_typed_input);
     * switch('' . $numeric) {
     *   case 'integer':
     *     work_with_integer((int) $weakly_typed_input);
     *     break;
     *   case 'float':
     *     work_with_float((float) $weakly_typed_input);
     *     break;
     *   default:
     *     return go_away();
     * }
     * if ($numeric
     * @endcode
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
     *      Checked stringified.
     *
     * @return string|bool
     *      String (integer|float) on pass,
     *      boolean false on validation failure.
     */
    public function numeric($var)
    {
        $v = '' . $var;
        $float = false;
        if (strpos($v, '.') !== false) {
            $count = 0;
            $v = str_replace('.', '', $v, $count);
            if ($count != 1) {
                return false;
            }
            $float = true;
        }
        // Yes, ctype_... returns false on ''.
        if (ctype_digit($v)) {
            return $float ? 'float' : 'integer';
        }
        return false;
    }

    /**
     * Non-negative integer or stringed integer.
     *
     * If negative integer should pass, use numeric()
     * and then check it's return value.
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
     *      Checked stringified.
     *
     * @return bool
     */
    public function digital($var) : bool
    {
        // Yes, ctype_... returns fals on ''.
        return ctype_digit('' . $var);
    }

    /**
     * @param $mixed $var
     * @param string $decimalMarker
     * @param string $thousandSep
     * @return bool
     *
    public function decimal($var, $decimalMarker, $thousandSep = '')
    {
        // Should integer|'integer' count as decimal?
        return false;
    }*/

    /**
     * Hexadeximal number (string).
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function hex($var, string $case = '') : bool
    {
        switch ($case) {
            case 'lower':
                $v = '' . $var;
                return $v === '' ? false : !!preg_match('/^[a-f\d]+$/', '' . $v);
            case 'upper':
                $v = '' . $var;
                return $v === '' ? false : !!preg_match('/^[A-F\d]+$/', '' . $v);
        }
        // Yes, ctype_... returns false on ''.
        return !!ctype_xdigit('' . $var);
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
    public function bit32($var) : bool
    {
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
    public function bit64($var) : bool
    {
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
    public function positive($var) : bool
    {
        return $var > 0;
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
    public function nonNegative($var) : bool
    {
        return $var >= 0;
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
    public function negative($var) : bool
    {
        return $var < 0;
    }

    /**
     * Numeric minimum.
     *
     * May produce false negative if args var and min both are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     * @param int|float $min
     *      Stringed number is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not integer or float.
     */
    public function min($var, $min) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            throw new InvalidArgumentException(
                'Arg min type[' . (!is_object($min) ? gettype($min) : get_class($min)) . '] is not integer or float.'
            );
        }
        return $var >= $min;
    }

    /**
     * Numeric maximum.
     *
     * May produce false negative if args var and max both are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not integer or float.
     */
    public function max($var, $max) : bool
    {
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException(
                'Arg max type[' . (!is_object($max) ? gettype($max) : get_class($max)) . '] is not integer or float.'
            );
        }
        return $var <= $max;
    }

    /**
     * Numeric range.
     *
     * May produce false negative if (at least) two of the args are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $var
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
    public function range($var, $min, $max) : bool
    {
        if (!is_int($min) && !is_float($min)) {
            throw new InvalidArgumentException(
                'Arg min type[' . (!is_object($max) ? gettype($min) : get_class($min)) . '] is not integer or float.'
            );
        }
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException(
                'Arg max type[' . (!is_object($max) ? gettype($max) : get_class($max)) . '] is not integer or float.'
            );
        }
        if ($max < $min) {
            throw new InvalidArgumentException('Arg max[' .  $max . '] cannot be less than arg min[' .  $min . '].');
        }
        return $var >= $min && $var <= $max;
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * Valid UTF-8.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function unicode($var) : bool
    {
        $v = '' . $var;
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
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *
     * @return bool
     *      True on empty.
     */
    public function unicodePrintable($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true :
            (
                !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
                // Prefix space to avoid expensive type check (boolean false).
                && !strpos(' ' . $v, chr(127))
            );
    }

    /**
     * Unicode printable that allows newline and (default) carriage return.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function unicodeMultiLine($var, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->unicodePrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $var
            )
        );
    }

    /**
     * String minimum multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not non-negative.
     */
    public function unicodeMinLength($var, int $min) : bool
    {
        if ($min < 0) {
            throw new InvalidArgumentException('Arg min[' . $min . '] is not non-negative.');
        }
        $v = '' . $var;
        if ($v === '') {
            return $min == 0;
        }
        return $this->unicode->strlen($v) >= $min;
    }

    /**
     * String maximum multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not non-negative.
     */
    public function unicodeMaxLength($var, int $max) : bool
    {
        if ($max < 0) {
            throw new InvalidArgumentException('Arg max[' . $max . '] is not non-negative.');
        }
        $v = '' . $var;
        if ($v === '') {
            // Unlikely, but correct.
            return $max == 0;
        }
        return $this->unicode->strlen($v) <= $max;
    }

    /**
     * String exact multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg exact not non-negative.
     */
    public function unicodeExactLength($var, int $exact) : bool
    {
        if ($exact < 0) {
            throw new InvalidArgumentException('Arg exact[' . $exact . '] is not non-negative.');
        }
        $v = '' . $var;
        if ($v === '') {
            return $exact == 0;
        }
        return $this->unicode->strlen($v) == $exact;
    }


    // ASCII string secondaries.------------------------------------------------

    /**
     * Full ASCII; 0-127.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function ascii($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : !!preg_match('/^[[:ascii:]]+$/', $v);
    }

    /**
     * Allows ASCII except lower ASCII and DEL.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      True on empty.
     */
    public function asciiPrintable($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : (
            !strcmp($v, !!filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH))
            // Prefix space to avoid expensive type check (boolean false).
            && !strpos(' ' . $v, chr(127))
        );
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function asciiMultiLine($var, $noCarriageReturn = false) : bool
    {
        // Remove newline chars before checking if printable.
        return $this->asciiPrintable(
            str_replace(
                !$noCarriageReturn ? ["\r", "\n"] : "\n",
                '',
                '' . $var
            )
        );
    }

    /**
     * String minimum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $min
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg min not non-negative.
     */
    public function minLength($var, int $min) : bool
    {
        if ($min < 0) {
            throw new InvalidArgumentException('Arg min[' . $min . '] is not non-negative.');
        }
        return strlen('' . $var) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $max
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not non-negative.
     */
    public function maxLength($var, int $max) : bool
    {
        if ($max < 0) {
            throw new InvalidArgumentException('Arg max[' . $max . '] is not non-negative.');
        }
        return strlen('' . $var) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param int $exact
     *      Stringed integer is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg exact not non-negative.
     */
    public function exactLength($var, int $exact) : bool
    {
        if ($exact < 0) {
            throw new InvalidArgumentException('Arg exact[' . $exact . '] is not non-negative.');
        }
        return strlen('' . $var) == $exact;
    }


    // ASCII specials.----------------------------------------------------------

    /**
     * ASCII alphanumeric.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function alphaNum($var, string $case = '') : bool
    {
        $v = '' . $var;
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
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function name($var, string $case = '') : bool
    {
        $v = '' . $var;
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
     * Snake cased name: starts with alpha, followed by alphanum/underscore.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function snakeName($var, string $case = '') : bool
    {
        $v = '' . $var;
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
     * Lisp cased name: starts with alpha, followed by alphanum/hyphen.
     *
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function lispName($var, string $case = '') : bool
    {
        $v = '' . $var;
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
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $var
     *      Checked stringified.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function uuid($var, string $case = '') : bool
    {
        $v = '' . $var;
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
     * @see Validate::string()
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     *      False on empty.
     */
    public function base64($var) : bool
    {
        return !!preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $var);
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
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateTimeIso8601($var) : bool
    {
        $v = '' . $var;
        return strlen($v) <= 35
            && !!preg_match(
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
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateIso8601Local($var) : bool
    {
        $v = '' . $var;
        return strlen($v) == 10 && !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * Doesn't contain tags.
     *
     * NB: Returns true on empty ('') string.
     *
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function plainText($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? true : !strcmp($v, strip_tags($v));
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function ipAddress($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_IP);
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function url($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function httpUrl($var) : bool
    {
        $v = '' . $var;
        return $v === '' ? false : (
            strpos($v, 'http') === 0
            && !!filter_var('' . $v, FILTER_VALIDATE_URL)
        );
    }

    /**
     * @param mixed $var
     *      Checked stringified.
     *
     * @return bool
     */
    public function email($var) : bool
    {
        // FILTER_VALIDATE_EMAIL doesn't reliably require .tld.
        $v = '' . $var;
        return $v === '' ? false : (
            !!filter_var($v, FILTER_VALIDATE_EMAIL)
            && !!preg_match('/\.[a-zA-Z\d]+$/', $v)
        );
    }
}
