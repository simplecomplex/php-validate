<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleTraits;

use SimpleComplex\Validate\Type;
use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 *
 * @package SimpleComplex\Validate
 */
trait EnumVersatileTrait
{
    /**
     * @param float $allowed
     * @param float $subject
     *
     * @return bool
     */
    protected function helperCompareFloat(float $allowed, float $subject) : bool
    {
        return abs($allowed - $subject) < PHP_FLOAT_EPSILON;
    }

    /**
     * Subject strictly equal to a bucket of an array.
     *
     * Checks whether all allowed values are bool|int(|float)|string|null.
     * @see TypeRulesTrait::equatable()
     * @see TypeRulesTrait::scalarNull()
     *
     * @param mixed $subject
     * @param mixed[] $allowedValues
     *      [
     *          0: some scalar (not float)
     *          1: null
     *          3: other scalar (not float)
     *      ]
     *
     * @return bool
     *
     * @throws InvalidRuleException
     *      Failure to determine whether the enum rule accepts float.
     * @throws InvalidArgumentException
     *      Arg $allowedValues is empty.
     *      A bucket of arg $allowedValues is not type-wise acceptable.
     */
    public function enum($subject, array $allowedValues) : bool
    {
        if (!$allowedValues) {
            throw new InvalidArgumentException('Arg $allowedValues is empty.');
        }

        /**
         * Get 'enum' type from the validator's lists of rule types.
         * @see Type
         * From the outset 'enum' is defined as a pattern rule.
         * @see \SimpleComplex\Validate\Interfaces\PatternRulesInterface::MINIMAL_PATTERN_RULES
         * @see \SimpleComplex\Validate\AbstractValidator::PATTERN_RULES
         * But if the current validator is _checked_ then the rule will (as all
         * pattern rules) be listed as a type-checking rule.
         * @see \SimpleComplex\Validate\Validator::TYPE_RULES
         * @var int $type
         */
        $type = static::TYPE_RULES['enum'] ?? static::PATTERN_RULES['enum'];
        if (!$type) {
            throw new InvalidRuleException(
                __CLASS__ . '::TYPE_RULES|PATTERN_RULES misses type of rule \'enum\''
                . ', thus cannot determine whether this rule accepts float.'
            );
        }
        $pass_null = $pass_float = false;
        switch ($type) {
            case Type::EQUATABLE:
                break;
            case Type::EQUATABLE_NULLABLE:
                $pass_null = true;
                break;
            case Type::SCALAR:
                $pass_float = true;
                break;
            default:
                /** @see Type::SCALAR_NULLABLE */
                $pass_null = $pass_float = true;
        }

        $is_null = $is_float = false;
        if ($subject === null) {
            if (!$pass_null) {
                return false;
            }
            $is_null = true;
        }
        elseif (is_float($subject)) {
            if (!$pass_float) {
                return false;
            }
            $is_float = true;
        }
        elseif (!is_scalar($subject)) {
            return false;
        }

        $i = -1;
        foreach ($allowedValues as $allowed) {
            ++$i;
            if ($is_null) {
                if ($allowed === null) {
                    return true;
                }
            }
            elseif ($is_float) {
                if (is_float($allowed) && $this->helperCompareFloat($allowed, $subject)) {
                    return true;
                }
            }
            elseif (is_scalar($allowed)) {
                if ($subject === $allowed) {
                    return true;
                }
            }
            else {
                throw new InvalidArgumentException(
                    'Arg $allowedValues bucket ' . $i . ' type[' . Helper::getType($allowed)
                    . '] is not scalar.'
                );
            }
        }

        return false;
    }

}
