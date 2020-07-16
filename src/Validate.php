<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

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
 * @dependency-injection-container-id validate
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
            // IDE: child class constructor may have parameters.
            (static::$instanceByClass[$class] = new static(...$constructorParams));
    }

    /**
     * Non-rule methods.
     *
     * @see Validate::getNonRuleMethods()
     *
     * Keys are property names, values may be anything.
     * Allows a child class to extend parent's list by doing
     * const NON_RULE_METHODS = [
     *   'someMethod' => true,
     * ] + ParentClass::NON_RULE_METHODS;
     *
     * @var mixed[]
     */
    const NON_RULE_METHODS = [
        'getInstance' => null,
        'flushInstance' => null,
        '__construct' => null,
        'getNonRuleMethods' => null,
        'getRuleMethods' => null,
        'getTypeMethods' => null,
        'getParameterMethods' => null,
        'getRulesRenamed' => null,
        '__call' => null,
        'challenge' => null,
        'challengeRecording' => null,
    ];

    /**
     * Methods that explicitly promise to check the subject's type
     * or that subject's type is a simple and sensibly coercible scalar.
     *
     * No type rule is allowed to take other arguments than the subject,
     * except the 'class' rule (which requires class name argument).
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then ValidationRuleSet makes a guess; ultimately string.
     * @see ValidationRuleSet::inferTypeCheckingRule()
     *
     * @var mixed[]
     */
    const TYPE_METHODS = [
        'boolean' => null,
        'bit' => null,
        'number' => null,
        'integer' => null,
        'float' => null,
        'string' => null,
        'null' => null,
        'resource' => null,
        'numeric' => null,
        'digital' => null,
        'object' => null,
        'class' => null,
        'array' => null,
        'container' => null,
        'iterable' => null,
        'loopable' => null,
        'indexedIterable' => null,
        'keyedIterable' => null,
        'indexedLoopable' => null,
        'keyedLoopable' => null,
        'indexedArray' => null,
    ];

    /**
     * Methods of this class that takes more arguments than just the subject.
     *
     * The arguments are required if the method bucket is true.
     *
     * @see Validate::getParameterMethods()
     *
     * @var bool[]
     */
    const PARAMETER_METHODS = [
        'enum' => true,
        'regex' => true,
        'class' => true,
        'min' => true,
        'max' => true,
        'range' => true,
        'unicodeMultiLine' => false,
        'unicodeMinLength' => true,
        'unicodeMaxLength' => true,
        'unicodeExactLength' => true,
        'hex' => false,
        //'asciiMultiLine' => false,
        'minLength' => true,
        'maxLength' => true,
        'exactLength' => true,
        'alphaNum' => false,
        'name' => false,
        'snakeName' => false,
        'lispName' => false,
        'uuid' => false,
        'timeISO8601' => false,
        'dateTimeISO8601' => false,
        'dateTimeISO8601Zonal' => false,
        'dateTimeISOUTC' => false,
    ];

    /**
     * New rule name by old rule name.
     *
     * @see Validate::getRulesRenamed()
     *
     * @var string[]
     */
    const RULES_RENAMED = [
        'dateIso8601Local' => 'dateISO8601Local',
        'dateTimeIso8601' => 'dateTimeISO8601',
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
     * @see Validate::getNonRuleMethods()
     *
     * @var string[]
     */
    protected $nonRuleMethods = [];

    /**
     * @see Validate::getRuleMethods()
     *
     * @var string[]
     */
    protected $ruleMethods = [];

    /**
     * @see Validate::getTypeMethods()
     *
     * @var string[]
     */
    protected $typeMethods = [];

    /**
     * @see Validate::getInstance()
     */
    public function __construct()
    {
    }

    /**
     * Lists names of public methods that aren't validation rule methods.
     *
     * @return string[]
     */
    public function getNonRuleMethods() : array
    {
        if (!$this->nonRuleMethods) {
            $this->nonRuleMethods = array_keys(static::NON_RULE_METHODS);
        }
        return $this->nonRuleMethods;
    }

    /**
     * Lists names of validation rule methods.
     *
     * @return string[]
     *
     * @throws \TypeError  Propagated.
     * @throws \InvalidArgumentException  Propagated.
     */
    public function getRuleMethods() : array
    {
        if (!$this->ruleMethods) {
            $this->ruleMethods = array_diff(
                Helper::getPublicMethods($this),
                $this->getNonRuleMethods()
            );
        }
        return $this->ruleMethods;
    }

    /**
     * Lists names of rule methods that explicitly promise to check
     * the subject's type.
     *
     * If the source of a validation rule set (e.g. JSON) doesn't contain any
     * of these methods then ValidationRuleSet makes a guess; ultimately string.
     * @see ValidationRuleSet::inferTypeCheckingRule()
     *
     * @return string[]
     */
    public function getTypeMethods() : array
    {
        if (!$this->typeMethods) {
            $this->typeMethods = array_keys(static::TYPE_METHODS);
        }
        return $this->typeMethods;
    }

    /**
     * Lists rule methods that accept/require other arguments(s) than subject.
     *
     * Key is method name, value is (bool) whether arguments(s) are required.
     *
     * @return bool[]
     */
    public function getParameterMethods() : array
    {
        return static::PARAMETER_METHODS;
    }

    /**
     * Lists rule methods renamed.
     *
     * Keys is old name, value new name.
     *
     * @return string[]
     */
    public function getRulesRenamed() : array
    {
        return static::RULES_RENAMED;
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
     * Stops on first failure.
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
     * Doesn't stop on failure, continues until the end of the ruleset.
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
     * @param string $keyPath
     *      Name of element to validate, or key path to it.
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challengeRecording($subject, $ruleSet, string $keyPath = 'root') : array
    {
        $validate_by_rules = new ValidateAgainstRuleSet($this, [
            'recordFailure' => true,
        ]);

        $passed = $validate_by_rules->challenge($subject, $ruleSet, $keyPath);
        return [
            'passed' => $passed,
            'record' => $passed ? [] : $validate_by_rules->getRecord(),
        ];
    }


    // Rules.-----------------------------------------------------------------------------------------------------------

    // Type indifferent.--------------------------------------------------------

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
                    'Arg allowedValues bucket ' . $i . ' type[' . Helper::getType($allowed) . '] is not scalar or null.'
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
     *      Checked stringified, and accepts stringable object.
     * @param string $pattern
     *      /regular expression/
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg pattern empty.
     */
    public function regex($subject, string $pattern) : bool
    {
        if (!$pattern) {
            throw new InvalidArgumentException('Arg pattern is empty.');
        }
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        return !!preg_match($pattern, '' . $subject);
    }

    // Type checkers.-----------------------------------------------------------

    /**
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
    public function float($subject) : bool
    {
        return is_float($subject);
    }

    /**
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function null($subject) : bool
    {
        return $subject === null;
    }

    /**
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
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
     * Integer, float or stringed integer/float (but not e-notation).
     *
     * Contrary to PHP native is_numeric() this method doesn't allow
     * leading plus nor leading space nor e-notation.
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
        if (!is_string($subject)) {
            return false;
        }
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
        if ($v[0] === '-') {
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
        // Yes, ctype_... returns fals on ''.
        return ctype_digit('' . $subject);
    }

    /**
     * @param mixed $subject
     * @param string $decimalMarker
     * @param string $thousandSep
     * @return bool
     *
    public function decimal($subject, $decimalMarker, $thousandSep = '')
    {
        // Should integer|'integer' count as decimal?
        return false;
    }*/


    // Containers.--------------------------------------------------------------

    /**
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
     * @uses is_a()
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
        return $subject && is_object($subject) && is_a($subject, $className);
    }

    /**
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
     *
     * @see Validate::loopable()
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
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * Counter to iterable loopable allows non-Traversable object,
     * except if (also) ArrayAccess.
     *
     * Non-Traversable ArrayAccess is (hopefully) the only relevant container
     * class/interface that isn't iterable.
     *
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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
     * Promises type safety.
     * @see Validate::TYPE_METHODS
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


    // Numeric secondaries.-----------------------------------------------------

    /**
     * 32-bit integer.
     *
     * @see Validate::number()
     * @see Validate::integer()
     * @see Validate::float()
     * @see Validate::numeric()
     * @see Validate::digital()
     *
     * @param mixed $subject
     *
     * @return bool
     */
    public function bit32($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
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
        if ($subject === null) {
            return false;
        }
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
        return $subject !== null && $subject >= 0;
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
            throw new InvalidArgumentException('Arg min type[' . Helper::getType($min) . '] is not integer or float.');
        }
        if ($subject === null) {
            return false;
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
            throw new InvalidArgumentException('Arg max type[' . Helper::getType($max) . '] is not integer or float.');
        }
        if ($subject === null) {
            return false;
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
            throw new InvalidArgumentException('Arg min type[' . Helper::getType($min) . '] is not integer or float.');
        }
        if (!is_int($max) && !is_float($max)) {
            throw new InvalidArgumentException('Arg max type[' . Helper::getType($max) . '] is not integer or float.');
        }
        if ($max < $min) {
            throw new InvalidArgumentException('Arg max[' .  $max . '] cannot be less than arg min[' .  $min . '].');
        }
        if ($subject === null) {
            return false;
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
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function unicode($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function unicodePrintable($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function unicodeMultiLine($subject, $noCarriageReturn = false) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
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
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
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
     * NB: Does not check if valid UTF-8; use 'unicode' rule before this.
     *
     * @see Validate::unicode()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * NB: Returns true on empty ('') string.
     *
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function ascii($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      True on empty.
     */
    public function asciiPrintable($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     * @param boolean $noCarriageReturn
     *
     * @return bool
     *      True on empty.
     */
    public function asciiMultiLine($subject, $noCarriageReturn = false) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        return strlen('' . $subject) >= $min;
    }

    /**
     * String maximum byte/ASCII length.
     *
     * @see Validate::string()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        return strlen('' . $subject) <= $max;
    }

    /**
     * String exact byte/ASCII length.
     *
     * @see Validate::string()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
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
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function alphaNum($subject, string $case = '') : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function name($subject, string $case = '') : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * @see Validate::string()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * @see Validate::string()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * @see Validate::string()
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
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     * @param string $case
     *      Values: lower|upper, otherwise ignored.
     *
     * @return bool
     *      False on empty.
     */
    public function uuid($subject, string $case = '') : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     *      False on empty.
     */
    public function base64($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        return !!preg_match('/^[a-zA-Z\d\+\/\=]+$/', '' . $subject);
    }

    /**
     * Max number of datetime ISO-8601 sub seconds digits.
     *
     * PHP \DateTime constructor max is 8 (PHP 7.0).
     * MS SQL date parser max is 9 (SQL Server 2017).
     */
    const DATETIME_ISO8601_SUBSECONDS_MAX = 8;

    /**
     * Ultimate catch-all ISO-8601 date/datetime timestamp.
     *
     * Positive timezone may be indicated by plus or space, because plus tends
     * to become space when URL decoding.
     *
     * YYYY-MM-DD([T ]HH(:ii(:ss)?(.m{1,N})?)?(Z|[+- ]HH:?(II)?)?)?
     * The format is supported by native \DateTime constructor.
     *
     * @see Validate::DATETIME_ISO8601_SUBSECONDS_MAX
     * @see Validate::string()
     * @see Validate::dateISO8601Local()
     * @see Validate::dateTimeISO8601()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO8601_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateISO8601($subject, int $subSeconds = -1) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        if (strlen($v) == 10) {
            return !!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v);
        }
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO8601_SUBSECONDS_MAX : $subSeconds;
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function dateISO8601Local($subject) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * @see Validate::DATETIME_ISO8601_SUBSECONDS_MAX
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO8601_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function timeISO8601($subject, int $subSeconds = -1) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO8601_SUBSECONDS_MAX : $subSeconds;
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
     * @see Validate::DATETIME_ISO8601_SUBSECONDS_MAX
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO8601_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISO8601($subject, int $subSeconds = -1) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO8601_SUBSECONDS_MAX : $subSeconds;
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
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function dateTimeISO8601Local($subject) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
     * @see Validate::DATETIME_ISO8601_SUBSECONDS_MAX
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO8601_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISO8601Zonal($subject, int $subSeconds = -1) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO8601_SUBSECONDS_MAX : $subSeconds;
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
     * @see Validate::DATETIME_ISO8601_SUBSECONDS_MAX
     * @see Validate::string()
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     * @param int $subSeconds
     *      Max number of sub second digits.
     *      Negative: uses class constant DATETIME_ISO8601_SUBSECONDS_MAX.
     *      Zero: none.
     *
     * @return bool
     */
    public function dateTimeISOUTC($subject, int $subSeconds = -1) : bool
    {
        // Ugly method name because \DateTime uses strict acronym camelCasing.

        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        if (!$subSeconds) {
            $ss = 0;
            $m = '';
        } else {
            $ss = $subSeconds < 0 ? static::DATETIME_ISO8601_SUBSECONDS_MAX : $subSeconds;
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
     * NB: Returns true on empty ('') string.
     *
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function plainText($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        return $v === '' ? true : !strcmp($v, strip_tags($v));
    }

    /**
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function ipAddress($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_IP);
    }

    /**
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function url($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        return $v === '' ? false : !!filter_var($v, FILTER_VALIDATE_URL);
    }

    /**
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function httpUrl($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
        $v = '' . $subject;
        return $v === '' ? false : (
            strpos($v, 'http') === 0
            && !!filter_var('' . $v, FILTER_VALIDATE_URL)
        );
    }

    /**
     * @param mixed $subject
     *      Checked stringified, and accepts stringable object.
     *
     * @return bool
     */
    public function email($subject) : bool
    {
        if ($subject === null) {
            return false;
        }
        if (is_object($subject) && !method_exists($subject, '__toString')) {
            return false;
        }
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
