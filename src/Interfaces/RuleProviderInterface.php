<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

/**
 * Describes required properties of a class - a 'rule provider' - that can
 * provide validation rules for a ValidateAgainstRuleSet instance.
 *
 * Rule method directives
 * ----------------------
 * I  Type declaring the $subject parameter is illegal.
 * Because until everybody uses strict type mode, the outcome of passing an
 * argument of other type to a type declared parameter is ambiguous; coercion
 * or TypeError(?).
 * II  Illegal rule method names:
 * - optional, alternativeEnum, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * @package SimpleComplex\Validate
 */
interface RuleProviderInterface
{
    /**
     * Names of methods of the rule provider that a ValidateAgainstRuleSet
     * instance should never call.
     *
     * @return array
     *
     * @see Validate::getNonRuleMethods()
     */
    public function getNonRuleMethods() : array;

    /**
     * Lists rule methods that explicitly promise to check the subject's type.
     *
     * @return array
     *
     * @see Validate::getTypeMethods()
     */
    public function getTypeMethods() : array;

    /**
     * Lists rule methods that accept/require other arguments(s) than subject.
     *
     * @return array
     *
     * @see Validate::getParameterMethods()
     */
    public function getParameterMethods() : array;

    /**
     * Subject is falsy or array|object is empty.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $subject
     *
     * @return bool
     *
     * @see Validate::empty()
     */
    public function empty($subject) : bool;

    /**
     * Subject is not falsy or array|object is non-empty.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $subject
     *
     * @return bool
     *
     * @see Validate::nonEmpty()
     */
    public function nonEmpty($subject) : bool;

    /**
     * Checks for equality against a list of values.
     *
     * Compares type strict, and allowed values must be scalar or null.
     *
     * The method must log or throw exception if arg allowedValues isn't a non-empty array.
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
     * @see Validate::enum()
     */
    public function enum($subject, array $allowedValues) : bool;

    /**
     * Array or object.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::container()
     */
    public function container($subject);

    /**
     * Array or Traversable object.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::iterable()
     */
    public function iterable($subject);

    /**
     * Array or Traversable object, or non-Traversable non-ArrayAccess object.
     *
     * 'arrayAccess' is a Traversable ArrayAccess object.
     *
     * @param mixed $subject
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::loopable()
     */
    public function loopable($subject);
}
