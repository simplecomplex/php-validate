<?php

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Describes required properties of a class that can provide validation rules
 * for a ValidateByRules instance.
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
     * There must be an 'enum' method, because ValidateByRules uses it for it's 'fallbackEnum' rule.
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
}
