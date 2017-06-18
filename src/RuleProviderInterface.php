<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Validate;

use Psr\Log\LoggerInterface;

/**
 * Describes required properties of a class - a 'rule provider' - that can
 * provide validation rules for a ValidateByRules instance.
 *
 * Rule method directives
 * ----------------------
 * I  Type declaring the $var parameter is illegal.
 * Because until everybody uses strict type mode, the outcome of passing an
 * argument of other type to a type declared parameter is ambiguous; coercion
 * or TypeError(?).
 * II  Illegal rule method names:
 * - optional, alternativeEnum, _elements_
 * @see ValidateByRules::NON_PROVIDER_RULES
 *
 * Referring a ValidateByRules instance is forbidden
 * -------------------------------------------------
 * Neither class nor instance can refer a ValidateByRules instance
 * because a ValidateByRules instance refers this (the rule provider);
 * circular referencing is unhealthy.
 *
 *
 * @package SimpleComplex\Validate
 */
interface RuleProviderInterface
{
    /**
     * Make logger available for a ValidateByRules instance.
     *
     * The logger is allowed be null (none), but preferably shouldn't be.
     * And ValidateByRules only gets the logger on demand; doesn't refer it.
     *
     * @return LoggerInterface|null
     */
    public function getLogger() /*: ?LoggerInterface*/;
    // PHP >7.1
    // public function getLogger() : ?LoggerInterface;

    /**
     * Methods of the class that a ValidateByRules instance should never call.
     *
     * @return array
     */
    public function getNonRuleMethods() : array;

    /**
     * There must be an 'empty' method, because ValidateByRules may need it.
     *
     * NB: Stringed zero - '0' - is _not_ empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function empty($var) : bool;

    /**
     * There must be a 'nonEmpty' method, because ValidateByRules may need it.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonEmpty($var) : bool;

    /**
     * There must be an 'enum' method, because ValidateByRules may need it.
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
     */
    public function enum($var, $allowedValues) : bool;

    /**
     * Object or array.
     *
     * Must return string (array|arrayAccess|iterable|object) on pass,
     * boolean false on validation failure.
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|arrayAccess|iterable|object) on pass,
     *      boolean false on validation failure.
     */
    public function container($var);

    /**
     * Iterable object or array.
     *
     * Must return string (array|arrayAccess|iterable) on pass,
     * boolean false on validation failure.
     *
     * @param mixed $var
     *
     * @return string|bool
     *      String (array|arrayAccess|iterable) on pass,
     *      boolean false on validation failure.
     */
    public function iterable($var);
}
