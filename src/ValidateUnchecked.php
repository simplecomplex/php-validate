<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

/**
 * High performance validator targeted recursive (ruleset) validation.
 *
 * Also usable 'manually' when using a type-checking rule before a pattern rule.
 * @see TypeRulesTrait
 * @see PatternRulesUncheckedTrait
 *
 *
 * Some string methods return true on empty
 * ----------------------------------------
 * Combine with the 'nonEmpty' rule if requiring non-empty.
 * Examples:
 * - unicode, unicodePrintable, unicodeMultiLine
 * - ascii, asciiPrintable, asciiMultiLine
 * - plainText
 * @see PatternRulesUncheckedTrait
 *
 *
 * Some methods return string on pass
 * ----------------------------------
 * Composite type checkers like:
 * - numeric, stringable, loopable
 * @see TypeRulesTrait
 *
 *
 * @package SimpleComplex\Validate
 */
class ValidateUnchecked extends AbstractValidate
{
}
