<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Unicode;
use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\BadMethodCallException;

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
 * that is: 1 for the subject to validate and max. 4 secondary
 * (specifying) parameters.
 * ValidateAgainstRuleSet::challenge() will err when given a rule
 * with more than 4 secondary args.
 *
 * Rule methods invalid arg checks
 * -------------------------------
 * Rule methods that take more arguments than the $subject to validate
 * must check those arguments for type/emptyness and throw exception
 * on such error.
 *
 * @dependency-injection-container validate
 *      Suggested ID of a global Validate instance.
 *
 * @package SimpleComplex\Validate
 */
class Validate implements RuleProviderInterface
{
    /**
     * Extending class must not override this variable.
     *
     * @var Validate[]
     * @final
     */
    protected static $instanceByClass;

    /**
     * Class-aware factory method.
     *
     * First object instantiated via this method, being class of class called on.
     *
     * Consider using a dependency injection container instead.
     * @see \SimpleComplex\Utils\Dependency
     * @see \Slim\Container
     *
     * @param mixed ...$constructorParams
     *      Validate child class constructor may have parameters.
     *
     * @return Validate|static
     */
    public static function getInstance(...$constructorParams)
    {
        $class = get_called_class();
        return static::$instanceByClass[$class] ??
            (static::$instanceByClass[$class] = new static(...$constructorParams));
    }

    /**
     * Don't ever access this - call getNonRuleMethods() instead.
     *
     * @see Validate::getNonRuleMethods()
     *
     * Methods of this class that a ValidateAgainstRuleSet instance
     * should never call.
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
     * Instance vars are not allowed to have state
     * -------------------------------------------
     * because that could affect the challenge() method, making calls leak state
     * to eachother.
     * Would void ValidateAgainstRuleSet::getInstance()'s warranty that
     * requested and returned instance are effectively identical.
     *
     * Var unicode do not infringe that principle, because Unicode instances
     * have no state. Neither do nonRuleMethods.
     *
     * @see Validate::challenge()
     * @see ValidateAgainstRuleSet::getInstance()
     */

    /**
     * @var Unicode
     */
    protected $unicode;

    /**
     * @see Validate::getNonRuleMethods()
     *
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
    }

    /**
     * Get list of methods of this class that are validation rule methods.
     *
     * Extending class declaring more non-rule methods must override this method.
     *
     * @return array
     */
    public function getNonRuleMethods() : array
    {
        if (!$this->nonRuleMethods) {
            // Root class does.
            $this->nonRuleMethods = self::NON_RULE_METHODS;
            // Extending class declaring non-rule methods should do:
            //$this->nonRuleMethods = array_unique(
            //    array_merge(
            //        parent::getNonRuleMethods(),
            //        self::NON_RULE_METHODS
            //    )
            //);
        }
        return $this->nonRuleMethods;
    }

    /**
     * By design, ValidateAgainstRuleSet::challenge() should not be able to call
     * a non-existent method of this class.
     * But external call to Validate::noSuchRule() is somewhat expectable.
     *
     * @see ValidateAgainstRuleSet::challenge()
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
     * Reuses the same ValidateAgainstRuleSet across Validate instances
     * and calls to this method.
     *
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return bool
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($subject, $ruleSet) : bool
    {
        // Re-uses instance on ValidateAgainstRuleSet rules.
        // Since we pass this object to the ValidateAgainstRuleSet instance,
        // we shan't refer the ValidateAgainstRuleSet instance directly.
        return ValidateAgainstRuleSet::getInstance(
            $this
        )->challenge($subject, $ruleSet);
    }

    /**
     * Validate by a list of rules, recording validation failures.
     *
     * Creates a new ValidateAgainstRuleSet instance on every call.
     *
     * @code
     * $good_bike = Validate::make()->challengeRecording($bike, $rules);
     * if (empty($good_bike['passed'])) {
     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
     * }
     * @endcode
     *
     * @param mixed $subject
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
    public function challengeRecording($subject, $ruleSet)
    {
        $validate_by_rules = new ValidateAgainstRuleSet($this, [
            'recordFailure' => true,
        ]);

        $validate_by_rules->challenge($subject, $ruleSet);
        $record = $validate_by_rules->getRecord();

        return [
            'passed' => !$record,
            'record' => $record,
        ];
    }


    // Rules.-----------------------------------------------------------------------------------------------------------

    // Type indifferent.--------------------------------------------------------

    /**
     * Subject is falsy or array|object is empty.
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

    /**
     * Compares type strict, and allowed values must be scalar or null.
     *
     * @param mixed $subject
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
    public function enum($subject, array $allowedValues) : bool
    {
        if (!$allowedValues) {
            throw new InvalidArgumentException('Arg allowedValues is empty.');
        }
        if ($subject !== null && !is_scalar($subject)) {
            return false;
        }
        $i = -1;
        foreach ($allowedValues as $allowed) {
            ++$i;
            if ($allowed !== null && !is_scalar($allowed)) {
                throw new InvalidArgumentException(
                    'Arg allowedValues bucket ' . $i . ' type[' . Utils::getType($allowed) . '] is not scalar or null.'
                );
            }
            if ($subject === $allowed) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $subject
     *      Checked stringified.
     * @param string $pattern
     *      /regular expression/
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg pattern empty
     */
    public function regex($subject, string $pattern) : bool
    {
        if (!$pattern) {
            throw new InvalidArgumentException('Arg pattern is empty.');
        }
        return !!preg_match($pattern, '' . $subject);
    }

    // Type checkers.-----------------------------------------------------------

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
     * @param mixed $subject
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function integer($subject) : bool
    {
        return is_int($subject);
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function float($subject) : bool
    {
        return is_float($subject);
    }

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function string($subject) : bool
    {
        return is_string($subject);
    }

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
     * @param mixed $subject
     *
     * @return bool
     */
    public function resource($subject) : bool
    {
        return is_resource($subject);
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
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function container($subject)
    {
        return is_array($subject) ? 'array' : (
            $subject && is_object($subject) ? (
                $subject instanceof \Traversable ? (
                    $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
                ) : 'object'
            ) : false
        );
    }

    /**
     * Array or Traversable object.
     *
     * Not very useful because stdClass _is_ iterable.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @see Validate::loopable()
     *
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     */
    public function iterable($subject)
    {
        return is_array($subject) ? 'array' : (
            $subject && $subject instanceof \Traversable ? (
                $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
            ) : false
        );
    }

    /**
     * Array or Traversable object, or non-Traversable non-ArrayAccess object.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * Counter to iterable loopable allows non-Traversable object,
     * except if (also) ArrayAccess.
     *
     * Non-Traversable ArrayAccess is (hopefully) the only relevant container
     * class/interface that isn't iterable.
     *
     * @param $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     */
    public function loopable($subject)
    {
        // Only difference vs container() is that non-Traversable ArrayAccess
        // doesn't pass here.
        return is_array($subject) ? 'array' : (
            $subject && is_object($subject) ? (
                $subject instanceof \Traversable ? (
                    $subject instanceof \ArrayAccess ? 'arrayAccess' : 'traversable'
                ) : (
                    $subject instanceof \ArrayAccess ? false : 'object'
                )
            ) : false
        );
    }

    /**
     * Empty or indexed iterable.
     *
     * @param $subject
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
     * @param $subject
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
     * @param $subject
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
     * @param $subject
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function object($subject) : bool
    {
        return $subject && is_object($subject);
    }

    /**
     * Is object and is of that class or interface, or has it as ancestor.
     *
     * @uses is_a()
     *
     * @param mixed $subject
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
        return $subject && is_object($subject) && is_a($subject, $className);
    }

    /**
     * @param mixed $subject
     *
     * @return bool
     */
    public function array($subject) : bool
    {
        return is_array($subject);
    }

    /**
     * Empty array or numerically indexed array.
     *
     * Does not check if the array's index is complete and correctly sequenced.
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


    // Numbers or stringed numbers.---------------------------------------------

    /**
     * Integer, float or stringed integer/float (but not e-notation).
     *
     * Contrary to PHP native is_numeric() this method doesn't allow
     * leading plus nor leading space.
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
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return string|bool
     *      String (integer|float) on pass,
     *      boolean false on validation failure.
     */
    public function numeric($subject)
    {
        // Why not native is_numeric()?
        // Native is_numeric() accepts (at least) e/E notation,
        // leading plus and leading space.
        // And (no blame ;-) it doesn't return type on success.
        $v = '' . $subject;
        $le = strlen($v);
        if (!$le) {
            return false;
        }
        // Remove leading hyphen, for later digital check.
        if ($v{0} === '-') {
            $v = substr($v, 1);
            --$le;
            if (!$le) {
                return false;
            }
        }
        $float = false;
        // Remove dot, for later digital check.
        if (strpos($v, '.') !== false) {
            if ($le == 1) {
                return false;
            }
            // Allow only single dot.
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
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function digital($subject) : bool
    {
        // Yes, ctype_... returns fals on ''.
        return ctype_digit('' . $subject);
    }

    /**
     * @param $mixed $subject
     * @param string $decimalMarker
     * @param string $thousandSep
     * @return bool
     *
    public function decimal($subject, $decimalMarker, $thousandSep = '')
    {
        // Should integer|'integer' count as decimal?
        return false;
    }*/

    /**
     * Hexadeximal number (string).
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit32($subject) : bool
    {
        return $subject >= -2147483648 && $subject <= 2147483647;
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit64($subject) : bool
    {
        return $subject >= -9223372036854775808 && $subject <= 9223372036854775807;
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function positive($subject) : bool
    {
        return $subject > 0;
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
     * @param mixed $subject
     *
     * @return bool
     */
    public function nonNegative($subject) : bool
    {
        return $subject >= 0;
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
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
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
            throw new InvalidArgumentException('Arg min type[' . Utils::getType($min) . '] is not integer or float.');
        }
        return $subject >= $min;
    }

    /**
     * Numeric maximum.
     *
     * May produce false negative if args subject and max both are float;
     * comparing floats is inherently imprecise.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digit()
     *
     * @param mixed $subject
     * @param int|float $max
     *      Stringed number is not accepted.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg max not integer or float.
     */
    public function max($subject, $max) : bool
    {
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException('Arg max type[' . Utils::getType($max) . '] is not integer or float.');
        }
        return $subject <= $max;
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
            throw new InvalidArgumentException('Arg min type[' . Utils::getType($min) . '] is not integer or float.');
        }
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException('Arg max type[' . Utils::getType($max) . '] is not integer or float.');
        }
        if ($max < $min) {
            throw new InvalidArgumentException('Arg max[' .  $max . '] cannot be less than arg min[' .  $min . '].');
        }
        return $subject >= $min && $subject <= $max;
    }


    // UTF-8 string secondaries.------------------------------------------------

    /**
     * Valid UTF-8.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::unicode()
     *
     * @param mixed $subject
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
        // filter_var() is not so picky about it's return value :-(
        if (
            !($filtered = filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW))
            || !is_string($filtered)
        ) {
            return false;
        }
        return !strcmp($v, $filtered)
            // Prefix space to avoid expensive type check (boolean false).
            && !strpos(' ' . $v, chr(127));
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
     * @param mixed $subject
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
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $subject
     *      Checked stringified.
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
        return $this->unicode->strlen($v) >= $min;
    }

    /**
     * String maximum multibyte/unicode length.
     *
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @param mixed $subject
     *      Checked stringified.
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
     * @param mixed $subject
     *      Checked stringified.
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
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
        return !strcmp($v, $filtered)
            // Prefix space to avoid expensive type check (boolean false).
            && !strpos(' ' . $v, chr(127));
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * Snake cased name: starts with alpha, followed by alphanum/underscore.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * Lisp cased name: starts with alpha, followed by alphanum/hyphen.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @see Validate::asciiLowerCase()
     * @see Validate::asciiUpperCase()
     *
     * @param mixed $subject
     *      Checked stringified.
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     *      False on empty.
     */
    public function base64($subject) : bool
    {
        return !!preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $subject);
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
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateTimeIso8601($subject) : bool
    {
        $v = '' . $subject;
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
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function dateIso8601Local($subject) : bool
    {
        $v = '' . $subject;
        return strlen($v) == 10 && !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
    }


    // Character set indifferent specials.--------------------------------------

    /**
     * Doesn't contain tags.
     *
     * NB: Returns true on empty ('') string.
     *
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function plainText($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? true : !strcmp($v, strip_tags($v));
    }

    /**
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function ipAddress($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_IP);
    }

    /**
     * @param mixed $subject
     *      Checked stringified.
     *
     * @return bool
     */
    public function url($subject) : bool
    {
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $subject
     *      Checked stringified.
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
     * @param mixed $subject
     *      Checked stringified.
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


    // Helpers.-----------------------------------------------------------------

    /**
     * @see Validate::indexedIterable()
     * @see Validate::indexedLoopable()
     * @see Validate::keyedIterable()
     * @see Validate::keyedLoopable()
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
                } else {
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
            } elseif ($loopable) {
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
