<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * enum rule accepting scalar|null subject and allowed values.
 *
 * Type-checking; usable for checked as well as unchecked validator.
 *
 * The rule-provider's type declaration of the 'enum' rule must be scalar|null.
 * @see Type::SCALAR_NULL
 * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface::MINIMAL_PATTERN_RULES
 * Unchecked validator:
 * @see \SimpleComplex\Validate\AbstractValidator::PATTERN_RULES
 * Checked validator:
 * @see \SimpleComplex\Validate\Validator::TYPE_RULES
 *
 * @mixin PatternRulesUncheckedTrait
 *
 * @package SimpleComplex\Validate
 */
trait EnumScalarNullTrait
{
    /**
     * Subject strictly equal to a bucket of an array.
     *
     * scalar|null implementation.
     *
     * @see PatternRulesUncheckedTrait::helperCompareFloat()
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar
     *          1: null
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
            throw new InvalidArgumentException('Arg $allowedValues is empty.');
        }

        if ($subject === null) {
            return in_array(null, $allowedValues, true);
        }

        if (!is_scalar($subject)) {
            return false;
        }

        if (!is_float($subject)) {
            return in_array($subject, $allowedValues, true);
        }

        foreach ($allowedValues as $allowed) {
            if (is_float($allowed) && $this->helperCompareFloat($allowed, $subject)) {
                return true;
            }
        }

        return false;
    }

}