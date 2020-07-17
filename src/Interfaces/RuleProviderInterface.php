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
 * Illegal rule method names
 * -------------------------
 * optional, allowNull, alternativeEnum, alternativeRuleSet, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * @package SimpleComplex\Validate
 */
interface RuleProviderInterface
{
//    /**
//     * Lists public methods that aren't validation rule methods.
//     *
//     * @return string[]
//     *
//     * @see Validate::getNonRuleMethods()
//     */
//    public function getNonRuleMethods() : array;

    /**
     * Lists validation rule methods.
     *
     * @return string[]
     *
     * @see Validate::getRuleMethods()
     */
    public function getRuleMethods() : array;

    /**
     * Lists rule methods that explicitly promise to check the subject's type.
     *
     * @return string[]
     *
     * @see Validate::getTypeMethods()
     */
    public function getTypeMethods() : array;

    /**
     * Methods that don't do type-checking, and what type they implicitly
     * expects.
     *
     * @return int[]
     *
     * @see Validate::getTypeInference()
     */
    public function getTypeInference() : array;

    /**
     * Lists rules renamed; current rule name by old rule name.
     *
     * @return string[]
     */
    public function getRulesRenamed() : array;

    /**
     * Two lists of numbers of required/allowed arguments.
     *
     * required: Number of required parameters, by rule method name.
     *
     * allowed: Number of allowed parameters - if none required
     *      or if allows more than required - by rule method name.
     *
     * @return int[][] {
     *      @var int[] $required
     *      @var int[] $allowed
     * }
     */
    public function getParameterSpecs() : array;


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
     * @param mixed $subject
     *
     * @return bool
     */
    public function string($subject) : bool;

    /**
     * Is object and is of that class or interface, or has it as ancestor.
     *
     * @param mixed $subject
     *      object to pass validation.
     * @param string $className
     *
     * @return bool
     */
    public function class($subject, string $className) : bool;

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
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::loopable()
     */
    public function loopable($subject);
}
