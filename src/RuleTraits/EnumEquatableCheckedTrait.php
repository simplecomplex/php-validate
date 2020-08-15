<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * enum rule accepting bool|int|string subject and allowed values.
 *
 * Type-checking; usable for checked as well as unchecked validator.
 *
 * The rule-provider's type declaration of the 'enum' rule must be
 * bool|int|string.
 * @see Type::EQUATABLE
 * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface::MINIMAL_PATTERN_RULES
 * Checked validator:
 * @see \SimpleComplex\Validate\Variants\EnumEquatableValidator::TYPE_RULES
 *
 * Possible rationale for an equatable enum
 * ----------------------------------------
 * You don't want null as an acceptable value in the allowed-values list,
 * because null could indicate error.
 * And floats cannot be compared exactly.
 *
 * @package SimpleComplex\Validate
 */
trait EnumEquatableCheckedTrait
{
    /**
     * Subject strictly equal to a bucket of an array.
     *
     * bool|int|string implementation.
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar, not float (nor null)
     *      ]
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Arg $allowedValues is empty.
     */
    public function enum($subject, array $allowedValues) : bool
    {
        if (!$allowedValues) {
            throw new InvalidArgumentException('Arg allowedValues is empty.');
        }

        if (is_scalar($subject) && !is_float($subject)) {
            return in_array($subject, $allowedValues, true);
        }

        return false;
    }

}
