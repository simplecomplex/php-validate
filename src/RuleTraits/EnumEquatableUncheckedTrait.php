<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

/**
 * enum rule accepting bool|int|string(|null) subject and allowed values.
 *
 * Not type-checking; only usable for unchecked validator.
 *
 * The rule-provider's type declaration of the 'enum' rule must be
 * bool|int|string or bool|int|string|null.
 * @see Type::EQUATABLE
 * @see Type::EQUATABLE_NULL
 * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface::MINIMAL_PATTERN_RULES
 * Unchecked validator:
 * @see \SimpleComplex\Validate\Variants\EnumEquatableUncheckedValidator::PATTERN_RULES
 *
 * NB: Usable for EQUATABLE _and_ EQUATABLE_NULL
 * because algo is indifferent to that.
 *
 * @package SimpleComplex\Validate
 */
trait EnumEquatableUncheckedTrait
{
    /**
     * Subject strictly equal to a bucket of an array.
     *
     * bool|int|string implementation.
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar, not float (null only if EQUATABLE_NULL)
     *      ]
     *
     * @return bool
     */
    public function enum($subject, array $allowedValues) : bool
    {
        return in_array($subject, $allowedValues, true);
    }

}
