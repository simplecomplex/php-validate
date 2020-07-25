<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Interfaces;

use SimpleComplex\Validate\Type;

/**
 * Pattern rules.
 *
 * There's no need for a pattern rule to be type-checking when used after
 * calling an appropriate type-checking rule.
 *
 * Equivalent non type-checking trait:
 * @see \SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait
 * Equivalent type-checking trait:
 * @see \SimpleComplex\Validate\RuleTraits\PatternRulesCheckedTrait
 *
 * Illegal rule method names
 * -------------------------
 * optional, nullable, alternativeEnum, alternativeRuleSet, tableElements, listItems
 * @see ValidateAgainstRuleSet::NON_PROVIDER_RULES
 *
 * @package SimpleComplex\Validate
 */
interface PatternRulesInterface
{
    // API constants.-----------------------------------------------------------

    /**
     * Rules that don't promise to check the subject's type.
     *
     * @see AbstractRuleProvider::getPatternRuleType()
     * @see AbstractRuleProvider::patternRuleToTypeRule()
     *
     * Used by RuleSetGenerator to secure a type checking rule when none such
     * mentioned in the source of a validation rule set (e.g. JSON).
     * @see AbstractRuleProvider::PATTERN_RULES
     * @see RuleSetGenerator::ensureTypeChecking()
     *
     * Implementing class may do:
     * const PATTERN_RULES = PatternRulesInterface::MINIMAL_PATTERN_RULES;
     * Or use use PHP array union(+), like:
     * const PATTERN_RULES = [
     *   'someRule' => Type::SOME_TYPE,
     * ] + PatternRulesInterface::MINIMAL_PATTERN_RULES;
     *
     * @var int[]
     */
    public const MINIMAL_PATTERN_RULES = [
        /**
         * Type-checking enum() only supports EQUATABLE (bool|int|string).
         * @see \SimpleComplex\Validate\RuleTraits\PatternRulesCheckedTrait::enum()
         */
        'enum' => Type::EQUATABLE,

        'bit32' => Type::NUMERIC,
        'bit64' => Type::NUMERIC,
        'positive' => Type::NUMERIC,
        'nonNegative' => Type::NUMERIC,
        'negative' => Type::NUMERIC,
        'min' => Type::NUMERIC,
        'max' => Type::NUMERIC,
        'range' => Type::NUMERIC,

        'maxDecimals' => Type::DECIMAL,

        /**
         * Consider stringable scalar, if stringable object not expected.
         * @see Type::STRINGABLE_SCALAR
         */
        'regex' => Type::STRINGABLE,
        'unicode' => Type::STRINGABLE,
        'unicodePrintable' => Type::STRINGABLE,
        'unicodeMultiLine' => Type::STRINGABLE,
        'unicodeMinLength' => Type::STRINGABLE,
        'unicodeMaxLength' => Type::STRINGABLE,
        'unicodeExactLength' => Type::STRINGABLE,
        'hex' => Type::STRINGABLE,
        'ascii' => Type::STRINGABLE,
        'asciiPrintable' => Type::STRINGABLE,
        'asciiMultiLine' => Type::STRINGABLE,
        'minLength' => Type::STRINGABLE,
        'maxLength' => Type::STRINGABLE,
        'exactLength' => Type::STRINGABLE,
        'alphaNum' => Type::STRINGABLE,
        'name' => Type::STRINGABLE,
        'camelName' => Type::STRINGABLE,
        'snakeName' => Type::STRINGABLE,
        'lispName' => Type::STRINGABLE,
        'uuid' => Type::STRINGABLE,
        'base64' => Type::STRINGABLE,

        // Datetimes cannot be int|float.
        'dateISO' => Type::STRING_STRINGABLE_OBJECT,
        'dateISOLocal' => Type::STRING_STRINGABLE_OBJECT,
        'timeISO' => Type::STRING_STRINGABLE_OBJECT,
        'dateTimeISO' => Type::STRING_STRINGABLE_OBJECT,
        'dateTimeISOLocal' => Type::STRING_STRINGABLE_OBJECT,
        'dateTimeISOZonal' => Type::STRING_STRINGABLE_OBJECT,
        'dateTimeISOUTC' => Type::STRING_STRINGABLE_OBJECT,

        /**
         * Consider stringable scalar, if stringable object not expected.
         * @see Type::STRINGABLE_SCALAR
         */
        'plainText' => Type::STRINGABLE,
        'ipAddress' => Type::STRINGABLE,
        'url' => Type::STRINGABLE,
        'httpUrl' => Type::STRINGABLE,
        'email' => Type::STRINGABLE,
    ];

    /**
     * Number of required parameters, by rule name.
     *
     * @var int[]
     */
    public const PATTERN_PARAMS_REQUIRED = [
        'enum' => 1,
        'min' => 1,
        'max' => 1,
        'range' => 2,
        'maxDecimals' => 1,
        'regex' => 1,
        'unicodeMinLength' => 1,
        'unicodeMaxLength' => 1,
        'unicodeExactLength' => 1,
        'minLength' => 1,
        'maxLength' => 1,
        'exactLength' => 1,
    ];

    /**
     * Number of allowed parameters - if none required
     * or if allows more than required - by rule name.
     *
     * @var int[]
     */
    public const PATTERN_PARAMS_ALLOWED = [
        'unicodeMultiLine' => 1,
        'hex' => 1,
        'asciiMultiLine' => 1,
        'alphaNum' => 1,
        'name' => 1,
        'camelName' => 1,
        'snakeName' => 1,
        'lispName' => 1,
        'uuid' => 1,
        'dateISO' => 1,
        'timeISO' => 1,
        'dateTimeISO' => 1,
        'dateTimeISOZonal' => 1,
        'dateTimeISOUTC' => 1,
    ];

    /**
     * New rule name by old rule name.
     *
     * @see AbstractRuleProvider::getRule()
     *
     * @var string[]
     */
    public const PATTERN_RULES_RENAMED = [
        'dateISO8601' => 'dateISO',
        'dateISO8601Local' => 'dateISOLocal',
        'timeISO8601' => 'timeISO',
        'dateTimeISO8601' => 'dateTimeISO',
        'dateTimeISO8601Local' => 'dateTimeISOLocal',
        'dateTimeISO8601Zonal' => 'dateTimeISOZonal',
    ];


    // Rule constants.----------------------------------------------------------

    /**
     * Flags controlling behaviours of rules.
     *
     * @var mixed[]
     */
    public const PATTERN_RULE_FLAGS = [
        /**
         * @see \SimpleComplex\Validate\RuleTraits\PatternRulesUncheckedTrait::dateTimeISO()
         */
        'DATETIME_ISO_SUBSECONDS_MAX' => 8,
    ];


    // Specials.----------------------------------------------------------------

    public function enum($subject, array $allowedValues) : bool;


    // Numeric secondaries.-----------------------------------------------------

    public function bit32($subject) : bool;

    public function bit64($subject) : bool;

    public function positive($subject) : bool;

    public function nonNegative($subject) : bool;

    public function negative($subject) : bool;

    public function min($subject, $min) : bool;

    public function max($subject, $max) : bool;

    public function range($subject, $min, $max) : bool;

    public function maxDecimals($subject, int $max) : bool;


    // String character set indifferent.----------------------------------------

    public function regex($subject, string $pattern) : bool;

    
    // UTF-8 string secondaries.------------------------------------------------

    public function unicode($subject) : bool;

    public function unicodePrintable($subject) : bool;

    public function unicodeMultiLine($subject, $noCarriageReturn = false) : bool;

    public function unicodeMinLength($subject, int $min) : bool;

    public function unicodeMaxLength($subject, int $max) : bool;

    public function unicodeExactLength($subject, int $exact) : bool;


    // ASCII string secondaries.------------------------------------------------

    public function hex($subject, string $case = '') : bool;

    public function ascii($subject) : bool;

    public function asciiPrintable($subject) : bool;

    public function asciiMultiLine($subject, $noCarriageReturn = false) : bool;

    public function minLength($subject, int $min) : bool;

    public function maxLength($subject, int $max) : bool;

    public function exactLength($subject, int $exact) : bool;


    // ASCII specials.----------------------------------------------------------

    public function alphaNum($subject, string $case = '') : bool;

    public function name($subject, string $case = '') : bool;

    public function camelName($subject, string $case = '') : bool;

    public function snakeName($subject, string $case = '') : bool;

    public function lispName($subject, string $case = '') : bool;

    public function uuid($subject, string $case = '') : bool;

    public function base64($subject) : bool;

    public function dateISO($subject, int $subSeconds = -1) : bool;

    public function dateISOLocal($subject) : bool;

    public function timeISO($subject, int $subSeconds = -1) : bool;

    public function dateTimeISO($subject, int $subSeconds = -1) : bool;

    public function dateTimeISOLocal($subject) : bool;

    public function dateTimeISOZonal($subject, int $subSeconds = -1) : bool;

    public function dateTimeISOUTC($subject, int $subSeconds = -1) : bool;


    // Character set indifferent specials.--------------------------------------

    public function plainText($subject) : bool;

    public function ipAddress($subject) : bool;

    public function url($subject) : bool;

    public function httpUrl($subject) : bool;

    public function email($subject) : bool;
}