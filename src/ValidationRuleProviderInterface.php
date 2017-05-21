<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Describes required properties of a class that can provide validation rules
 * for a ValidateByRules instance.
 *
 * Rule methods must not have more than 5 parameters, that is:
 * 1 for the var to validate and max. 4 secondary (specifying) parameters.
 *
 * Illegal rule method names:
 * - optional, allowOtherTypeEmpty, elements
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
    public function empty($var);

    /**
     * There must be a 'nonEmpty' method, because ValidateByRules may need it.
     *
     * NB: Stringed zero - '0' - _is_ non-empty.
     *
     * @param mixed $var
     *
     * @return bool
     */
    public function nonEmpty($var);

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
    public function enum($var, $allowedValues);

    /**
     * Array or object.
     *
     * Must be a superset of all other object and array type(ish) checkers.
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
