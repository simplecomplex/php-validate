<?php

declare(strict_types=1);
/*
 * Forwards compatility really; everybody will to this once.
 * But scalar parameter type declaration is no-go until then; coercion or TypeError(?).
 */

namespace SimpleComplex\Filter;

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
 * II  A rule method must not have more than 5 parameters, that is:
 * - 1 for the var to validate and max. 4 secondary (specifying) parameters
 * III  Illegal rule method names:
 * - optional, alternativeEnum, elements
 *
 *
 * Referring a ValidateByRules instance is forbidden
 * -------------------------------------------------
 * Neither class nor instance can refer a ValidateByRules instance
 * because a ValidateByRules instance refers this (the rule provider);
 * circular referencing is unhealthy.
 *
 *
 * @package SimpleComplex\Filter
 */
interface ValidationRuleProviderInterface
{
    /**
     * Make logger available for a ValidateByRules instance.
     *
     * The logger is allowed be null (none), but preferably shouldn't be.
     * And ValidateByRules only gets the logger on demand; doesn't refer it.
     *
     * @return LoggerInterface|null
     */
    public function getLogger();
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
    public function empty($var) : boolean;

    /**
     * There must be a 'nonEmpty' method, because ValidateByRules may need it.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonEmpty($var) : boolean;

    /**
     * There must be an 'enum' method, because ValidateByRules may need it.
     *
     * Compares type strict, and allowed values must be scalar or null.
     *
     * The method must log or throw exception if arg allowedValues isn't a non-empty array.
     *
     * @param mixed $var
     * @param array $allowedValues
     *  [
     *    0: some scalar
     *    1: null
     *    3: other scalar
     *  ]
     *
     * @return bool
     */
    public function enum($var, $allowedValues) : boolean;

    /**
     * Object or array.
     *
     * Must return string (array|object) on pass, boolean false on failure.
     *
     * Not related to PHP>=7 \DS\Collection (Traversable, Countable, JsonSerializable).
     *
     * @param mixed $var
     *
     * @return string|bool
     *  String (array|object) on pass, boolean false on failure.
     */
    public function collection($var);
}
