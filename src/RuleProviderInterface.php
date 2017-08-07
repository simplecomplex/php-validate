<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Validate;

/**
 * Describes required properties of a class - a 'rule provider' - that can
 * provide validation rules for a ValidateAgainstRuleSet instance.
 *
 * Rule method directives
 * ----------------------
 * I  Type declaring the $var parameter is illegal.
 * Because until everybody uses strict type mode, the outcome of passing an
 * argument of other type to a type declared parameter is ambiguous; coercion
 * or TypeError(?).
 * II  Illegal rule method names:
 * - optional, alternativeEnum, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * Referring a ValidateAgainstRuleSet instance is forbidden
 * --------------------------------------------------------
 * Neither class nor instance can refer a ValidateAgainstRuleSet instance
 * because a ValidateAgainstRuleSet instance refers this (the rule provider);
 * mutual referencing is unhealthy.
 *
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
     * There must be an 'empty' method; ValidateAgainstRuleSet may need it.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $var
     *
     * @return bool
     *
     * @see Validate::empty()
     */
    public function empty($var) : bool;

    /**
     * There must be a 'nonEmpty' method; ValidateAgainstRuleSet may need it.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     *
     * @see Validate::nonEmpty()
     */
    public function nonEmpty($var) : bool;

    /**
     * There must be an 'enum' method; ValidateAgainstRuleSet may need it.
     *
     * Compares type strict, and allowed values must be scalar or null.
     *
     * The method must log or throw exception if arg allowedValues isn't a non-empty array.
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
     * @see Validate::enum()
     */
    public function enum($var, array $allowedValues) : bool;

    /**
     * Object or array.
     *
     * Must return string (array|arrayAccess|traversable|object) on pass,
     * boolean false on validation failure.
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable|object) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::container()
     */
    public function container($var);

    /**
     * Iterable object or array.
     *
     * Must return string (array|arrayAccess|traversable) on pass,
     * boolean false on validation failure.
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|arrayAccess|traversable) on pass,
     *      boolean false on validation failure.
     *
     * @see Validate::iterable()
     */
    public function iterable($var);
}
