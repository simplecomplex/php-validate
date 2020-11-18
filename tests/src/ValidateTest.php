<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Validate;

use PHPUnit\Framework\TestCase;

use SimpleComplex\Validate\Interfaces\PatternRulesInterface;

use SimpleComplex\Validate\AbstractValidator;
use SimpleComplex\Validate\RuleSetValidator;
use SimpleComplex\Validate\CheckedValidator;
use SimpleComplex\Validate\Variants\EnumEquatableNullRuleSetValidator;
use SimpleComplex\Validate\Variants\EnumEquatableRuleSetValidator;
use SimpleComplex\Validate\Variants\EnumScalarRuleSetValidator;

use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;
use SimpleComplex\Validate\RuleSet\ValidationRuleSet;

use SimpleComplex\Validate\Exception\ValidationException;

use SimpleComplex\Time\Time;
use SimpleComplex\Tests\Validate\Entity\Stringable;
use SimpleComplex\Tests\Validate\Entity\NoModelExplorable;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/ValidateTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class ValidateTest extends TestCase
{
    /**
     * @return RuleSetValidator
     */
    public function testInstantiateRuleSetValidator()
    {
        $validator = RuleSetValidator::getInstance();
        static::assertInstanceOf(RuleSetValidator::class, $validator);
        return $validator;
    }

    /**
     * @return CheckedValidator
     */
    public function testInstantiateCheckedValidator()
    {
        $validator = new CheckedValidator();
        static::assertInstanceOf(CheckedValidator::class, $validator);
        return $validator;
    }

    /**
     * @see CheckedValidator::empty()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testEmpty()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertTrue($validator->empty(null));

        static::assertTrue($validator->empty(false));
        static::assertFalse($validator->empty(true));

        static::assertTrue($validator->empty(0));
        static::assertFalse($validator->empty(1));

        static::assertTrue($validator->empty(''));
        static::assertFalse($validator->empty(' '));
        static::assertFalse($validator->empty('0'));

        static::assertTrue($validator->empty([]));
        static::assertFalse($validator->empty([0]));

        $o = new \stdClass();
        static::assertTrue($validator->empty($o));
        $o->a = 0;
        static::assertFalse($validator->empty($o));

        $o = new \ArrayObject();
        static::assertTrue($validator->empty($o));
        $o[0] = 0;
        static::assertFalse($validator->empty($o));

        $o = new Stringable();
        static::assertFalse($validator->empty($o));

        $o = new NoModelExplorable();
        static::assertTrue($validator->empty($o));
        $o->someProp = 'some prop';
        static::assertFalse($validator->empty($o));
    }

    /**
     * @see CheckedValidator::nonEmpty()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testNonEmpty()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->nonEmpty(null));

        static::assertFalse($validator->nonEmpty(false));
        static::assertTrue($validator->nonEmpty(true));

        static::assertFalse($validator->nonEmpty(0));
        static::assertTrue($validator->nonEmpty(1));

        static::assertFalse($validator->nonEmpty(''));
        static::assertTrue($validator->nonEmpty(' '));
        static::assertTrue($validator->nonEmpty('0'));

        static::assertFalse($validator->nonEmpty([]));
        static::assertTrue($validator->nonEmpty([0]));

        $o = new \stdClass();
        static::assertFalse($validator->nonEmpty($o));
        $o->a = 0;
        static::assertTrue($validator->nonEmpty($o));

        $o = new \ArrayObject();
        static::assertFalse($validator->nonEmpty($o));
        $o[0] = 0;
        static::assertTrue($validator->nonEmpty($o));

        $o = new Stringable();
        static::assertFalse($validator->nonEmpty($o));

        $o = new NoModelExplorable();
        static::assertFalse($validator->nonEmpty($o));
        $o->someProp = 'some prop';
        static::assertTrue($validator->nonEmpty($o));
    }

    public function testNull()
    {
        $validator = $this->testInstantiateCheckedValidator();

        // Rules that don't require argument(s).
        $rule_methods = $validator->getRuleNames();
        foreach ($rule_methods as $ruleName) {
            $rule = $validator->getRule($ruleName);
            if ($rule->paramsRequired) {
                if ($rule->paramsRequired == 1) {
                    switch ($ruleName) {
                        // Type rules.----------------------
                        case 'class':
                            $arg1 = \stdClass::class;
                            break;
                        case 'minSize':
                        case 'maxSize':
                        case 'exactSize':
                            $arg1 = 0;
                            break;
                        // Pattern rules.-------------------
                        case 'enum':
                            $arg1 = [0];
                            break;
                        case 'min':
                        case 'max':
                            $arg1 = 0;
                            break;
                        case 'maxDecimals':
                            $arg1 = 1;
                            break;
                        case 'regex':
                            $arg1 = '/0/';
                            break;
                        case 'unicodeMinLength':
                        case 'unicodeMaxLength':
                        case 'unicodeExactLength':
                        case 'minLength':
                        case 'maxLength':
                        case 'exactLength':
                            $arg1 = 0;
                            break;
                        default:
                            throw new \LogicException(
                                'Missing argument for single-parametered rule[' . $ruleName . '].'
                            );
                    }
                    static::assertFalse($validator->{$ruleName}(null, $arg1), 'Rule method (false): ' . $ruleName);
                }
                elseif ($rule->paramsRequired == 2) {
                    switch ($ruleName) {
                        case 'range':
                            $arg1 = 0;
                            $arg2 = 1;
                            break;
                        default:
                            throw new \LogicException(
                                'Missing argument for double-parametered rule[' . $ruleName . '].'
                            );
                    }
                    static::assertFalse($validator->{$ruleName}(null, $arg1, $arg2), 'Rule method (false): ' . $ruleName);
                }
                else {
                    throw new \LogicException(
                        'Missing argument for double-parametered rule[' . $ruleName . '].'
                    );
                }
                continue;
            }
            switch ($ruleName) {
                case 'empty':
                case 'null':
                case 'scalarNull':
                case 'equatableNull':
                    static::assertTrue($validator->{$ruleName}(null), 'Rule method (true): ' . $ruleName);
                    break;
                default:
                    static::assertFalse($validator->{$ruleName}(null), 'Rule method (false): ' . $ruleName);
            }
        }
    }

    /**
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     */
    public function testAllowNull()
    {
        $validator = $this->testInstantiateRuleSetValidator();

        $ruleSet = [
            'nonNegative' => true,
        ];
        static::assertFalse($validator->validate(null, $ruleSet), 'Rule method (false): ' . 'nonNegative');
        $ruleSet = [
            'nonNegative' => true,
            'allowNull' => true,
        ];
        static::assertTrue($validator->validate(null, $ruleSet), 'Rule method (true): ' . 'nonNegative');
    }

    /**
     * @see CheckedValidator::enum()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testEnum()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->enum([], [[]]));
        $o = new \stdClass();
        static::assertFalse($validator->enum($o, [$o]));

        static::assertFalse($validator->enum(null, [0]));
        static::assertFalse($validator->enum(false, [0]));
        static::assertFalse($validator->enum(true, [0]));

        static::assertTrue($validator->enum(null, [null, false, true]));
        static::assertTrue($validator->enum(false, [false, true]));
        static::assertTrue($validator->enum(true, [false, true]));

        static::assertTrue($validator->enum(0, [0]));
        static::assertFalse($validator->enum('0', [0]));

        /**
         * Float and null are allowed.
         * @see Type::SCALAR_NULL
         * @see PatternRulesInterface::MINIMAL_PATTERN_RULES
         */
        static::assertTrue($validator->enum(null, [false, null]));
        static::assertTrue($validator->enum(0.1, [0.1]));
    }

    /**
     * @throws ValidationException
     */
    public function testEnumUncheckedRuleProviders()
    {
        $ruleProviders = [
            RuleSetValidator::class,
            EnumScalarRuleSetValidator::class,
            EnumEquatableNullRuleSetValidator::class,
            EnumEquatableRuleSetValidator::class,
        ];
        foreach ($ruleProviders as $class) {
            /** @var RuleSetValidator $validator */
            $validator = new $class();
            $ruleset = (new RuleSetFactory($validator))->make(
                [
                    'enum' => [
                        /**
                         * All validators' enums accept bool|int|string.
                         * @see Type::EQUATABLE
                         */
                        false,
                        0,
                        ''
                    ]
                ]
            );
            static::assertTrue($validator->validate(false, $ruleset));
            static::assertTrue($validator->validate(0, $ruleset));
            static::assertTrue($validator->validate('', $ruleset));
            static::assertFalse($validator->validate(true, $ruleset));
            static::assertFalse($validator->validate(1, $ruleset));
            static::assertFalse($validator->validate(' ', $ruleset));
            static::assertFalse($validator->validate([], $ruleset));
            static::assertFalse($validator->validate(new \stdClass(), $ruleset));
        }
    }

    /**
     * @throws ValidationException
     */
    public function testEnumUncheckedValidator()
    {
        $validator = new RuleSetValidator();
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    /**
                     * All validators' enums accept bool|int|string.
                     * @see Type::EQUATABLE
                     */
                    false,
                    0,
                    '',
                    /**
                     * RuleSetValidator enum furthermore accepts float|null.
                     * @see RuleSetValidator
                     * @see Type::SCALAR_NULL
                     */
                    null,
                    0.0
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
        static::assertTrue($validator->validate(null, $ruleset));
        static::assertTrue($validator->validate(0.0, $ruleset));

        static::expectException(ValidationException::class);
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    false,
                    0,
                    '',
                    null,
                    0.0,
                    // Not SCALAR_NULL.
                    new \stdClass(),
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
    }

    /**
     * @throws ValidationException
     */
    public function testEnumScalarUncheckedValidator()
    {
        $validator = new EnumScalarRuleSetValidator();
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    /**
                     * All validators' enums accept bool|int|string.
                     * @see Type::EQUATABLE
                     */
                    false,
                    0,
                    '',
                    /**
                     * EnumScalarRuleSetValidator enum furthermore accepts float.
                     * @see EnumScalarRuleSetValidator
                     * @see Type::SCALAR
                     */
                    0.0,
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
        static::assertFalse($validator->validate(null, $ruleset));
        static::assertTrue($validator->validate(0.0, $ruleset));

        static::expectException(ValidationException::class);
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    false,
                    0,
                    '',
                    0.0,
                    // Not SCALAR.
                    null,
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
    }

    /**
     * @throws ValidationException
     */
    public function testEnumEquatableNullUncheckedValidator()
    {
        $validator = new EnumEquatableNullRuleSetValidator();
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    /**
                     * All validators' enums accept bool|int|string.
                     * @see Type::EQUATABLE
                     */
                    false,
                    0,
                    '',
                    /**
                     * EnumEquatableNullRuleSetValidator enum furthermore accepts null.
                     * @see EnumEquatableNullRuleSetValidator
                     * @see Type::EQUATABLE_NULL
                     */
                    null
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
        static::assertTrue($validator->validate(null, $ruleset));
        static::assertFalse($validator->validate(0.0, $ruleset));

        static::expectException(ValidationException::class);
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    false,
                    0,
                    '',
                    // Not EQUATABLE_NULL.
                    0.0,
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
    }

    public function testEnumEquatableUncheckedValidator()
    {
        $validator = new EnumEquatableRuleSetValidator();
        static::expectException(ValidationException::class);
        $ruleset = (new RuleSetFactory($validator))->make(
            [
                'enum' => [
                    false,
                    0,
                    '',
                    // Not EQUATABLE.
                    0.0
                ]
            ]
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleset);
    }

    /**
     * @see CheckedValidator::regex()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testRegex()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->regex(null, '/0/'));

        static::assertTrue($validator->regex(0, '//'));
        static::assertTrue($validator->regex(1, '/1/'));

        static::assertTrue($validator->regex('a', '/a/'));
        static::assertFalse($validator->regex('a', '/b/'));
        static::assertFalse($validator->regex('a', '/\d/'));
    }

    /**
     * @see CheckedValidator::boolean()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testBoolean()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->boolean(null));
        static::assertTrue($validator->boolean(false));
        static::assertTrue($validator->boolean(true));

        static::assertFalse($validator->boolean(0));
        static::assertFalse($validator->boolean('0'));
        static::assertFalse($validator->boolean('a'));
    }

    /**
     * @see CheckedValidator::number()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testNumber()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->number(null));
        static::assertFalse($validator->number(false));
        static::assertFalse($validator->number(true));

        static::assertTrue($validator->number(0));
        static::assertTrue($validator->number(0.0));
        static::assertTrue($validator->number(1));
        static::assertTrue($validator->number(1.0));
        static::assertTrue($validator->number(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertTrue($validator->number(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validator->number('0'));
        static::assertFalse($validator->number('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validator->number($o));
        static::assertFalse($validator->number('' . $o));
    }

    /**
     * @see CheckedValidator::integer()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testInteger()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->integer(null));
        static::assertFalse($validator->integer(false));
        static::assertFalse($validator->integer(true));

        static::assertTrue($validator->integer(0));
        static::assertFalse($validator->integer(0.0));
        static::assertTrue($validator->integer(1));
        static::assertFalse($validator->integer(1.0));
        static::assertTrue($validator->integer(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertFalse($validator->integer(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validator->integer('0'));
        static::assertFalse($validator->integer('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validator->integer($o));
        static::assertFalse($validator->integer('' . $o));
    }

    /**
     * @see CheckedValidator::float()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testFloat()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->float(null));
        static::assertFalse($validator->float(false));
        static::assertFalse($validator->float(true));

        static::assertFalse($validator->float(0));
        static::assertTrue($validator->float(0.0));
        static::assertFalse($validator->float(1));
        static::assertTrue($validator->float(1.0));
        static::assertFalse($validator->float(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertTrue($validator->float(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validator->float('0'));
        static::assertFalse($validator->float('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validator->float($o));
        static::assertFalse($validator->float('' . $o));
    }

    public function testNumerics()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->integerString(''));
        static::assertFalse($validator->integerString(0));
        static::assertFalse($validator->integerString(1));
        static::assertTrue($validator->integerString('0'));
        static::assertTrue($validator->integerString('1'));
        static::assertFalse($validator->integerString(0.0));
        static::assertFalse($validator->integerString(0.1));
        static::assertFalse($validator->integerString('.0'));
        static::assertFalse($validator->integerString('0.0'));
        static::assertFalse($validator->integerString('.1'));
        static::assertFalse($validator->integerString('0.1'));
        static::assertFalse($validator->integerString('1.'));
        static::assertFalse($validator->integerString('0.'));
        static::assertFalse($validator->integerString('-0'));
        static::assertFalse($validator->integerString('-0.0'));
        static::assertFalse($validator->integerString(-1));
        static::assertTrue($validator->integerString('-1'));
        static::assertFalse($validator->integerString('+1'));
        static::assertFalse($validator->integerString(' +1'));
        static::assertFalse($validator->integerString('+ 1'));

        static::assertFalse($validator->floatString(''));
        static::assertFalse($validator->floatString(0));
        static::assertFalse($validator->floatString(1));
        static::assertFalse($validator->floatString('0'));
        static::assertFalse($validator->floatString('1'));
        static::assertFalse($validator->floatString(0.0));
        static::assertFalse($validator->floatString(0.1));
        static::assertTrue($validator->floatString('.0'));
        static::assertTrue($validator->floatString('0.0'));
        static::assertTrue($validator->floatString('.1'));
        static::assertTrue($validator->floatString('0.1'));
        static::assertTrue($validator->floatString('1.'));
        static::assertTrue($validator->floatString('0.'));
        static::assertFalse($validator->floatString('-0'));
        static::assertFalse($validator->floatString('-0.0'));
        static::assertFalse($validator->floatString(-1));
        static::assertFalse($validator->floatString('-1'));
        static::assertFalse($validator->floatString('+1'));
        static::assertFalse($validator->floatString(' +1'));
        static::assertFalse($validator->floatString('+ 1'));

        static::assertFalse($validator->numeric(''));
        static::assertTrue($validator->numeric(0));
        static::assertTrue($validator->numeric(1));
        static::assertTrue($validator->numeric('0'));
        static::assertTrue($validator->numeric('1'));
        static::assertTrue($validator->numeric(0.0));
        static::assertTrue($validator->numeric(0.1));
        static::assertTrue($validator->numeric('.0'));
        static::assertTrue($validator->numeric('0.0'));
        static::assertTrue($validator->numeric('.1'));
        static::assertTrue($validator->numeric('0.1'));
        static::assertTrue($validator->numeric('1.'));
        static::assertTrue($validator->numeric('0.'));
        static::assertFalse($validator->numeric('-0'));
        static::assertFalse($validator->numeric('-0.0'));
        static::assertTrue($validator->numeric(-1));
        static::assertTrue($validator->numeric('-1'));
        static::assertFalse($validator->numeric('+1'));
        static::assertFalse($validator->numeric(' +1'));
        static::assertFalse($validator->numeric('+ 1'));

        static::assertFalse($validator->digital(''));
        static::assertTrue($validator->digital(0));
        static::assertTrue($validator->digital(1));
        static::assertTrue($validator->digital('0'));
        static::assertTrue($validator->digital('1'));
        static::assertFalse($validator->digital(0.0));
        static::assertFalse($validator->digital(0.1));
        static::assertFalse($validator->digital('.0'));
        static::assertFalse($validator->digital('0.0'));
        static::assertFalse($validator->digital('.1'));
        static::assertFalse($validator->digital('0.1'));
        static::assertFalse($validator->digital('-0'));
        static::assertTrue($validator->digital(-100));
        static::assertTrue($validator->digital('-1'));

        static::assertFalse($validator->decimal(''));
        static::assertFalse($validator->decimal(0));
        static::assertFalse($validator->decimal(1));
        static::assertTrue($validator->decimal('0'));
        static::assertTrue($validator->decimal('1'));
        static::assertFalse($validator->decimal(0.0));
        static::assertFalse($validator->decimal(0.1));
        static::assertTrue($validator->decimal('.0'));
        static::assertTrue($validator->decimal('0.0'));
        static::assertTrue($validator->decimal('.1'));
        static::assertTrue($validator->decimal('0.1'));
        static::assertTrue($validator->decimal('1.'));
        static::assertTrue($validator->decimal('0.'));
        static::assertFalse($validator->decimal('-0'));
        static::assertFalse($validator->decimal('-0.0'));
        static::assertFalse($validator->decimal(-1));
        static::assertTrue($validator->decimal('-1'));
        static::assertFalse($validator->decimal('+1'));
        static::assertFalse($validator->decimal(' +1'));
        static::assertFalse($validator->decimal('+ 1'));

        static::assertFalse($validator->maxDecimals('0.0', 0));
        static::assertFalse($validator->maxDecimals('0.123', 2));
        static::assertTrue($validator->maxDecimals('0.123', 3));
    }

    /**
     * @see CheckedValidator::string()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testString()
    {
        $validator = $this->testInstantiateCheckedValidator();

        static::assertFalse($validator->string(null));
        static::assertFalse($validator->string(false));
        static::assertFalse($validator->string(true));

        static::assertFalse($validator->string(0));
        static::assertFalse($validator->string(0.0));
        static::assertFalse($validator->string(1));
        static::assertFalse($validator->string(1.0));
        static::assertFalse($validator->string(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertFalse($validator->string(constant('PHP_FLOAT_MAX')));
        }
        static::assertTrue($validator->string('0'));
        static::assertTrue($validator->string('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validator->string($o));
        static::assertTrue($validator->string('' . $o));
    }

    /**
     * @see CheckedValidator::dateDateTimeISO()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testSubjectStringCoercion()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $subject = new \stdClass();

        static::assertFalse($validator->regex($subject, '/./'));
        static::assertFalse($validator->unicode($subject));
        static::assertFalse($validator->unicodePrintable($subject));
        static::assertFalse($validator->unicodeMultiLine($subject));
        static::assertFalse($validator->unicodeMinLength($subject, 1));
        static::assertFalse($validator->unicodeMaxLength($subject, 1));
        static::assertFalse($validator->unicodeExactLength($subject, 1));
        static::assertFalse($validator->hex($subject));
        static::assertFalse($validator->ascii($subject));
        static::assertFalse($validator->asciiPrintable($subject));
        static::assertFalse($validator->asciiMultiLine($subject));
        static::assertFalse($validator->minLength($subject, 1));
        static::assertFalse($validator->maxLength($subject, 1));
        static::assertFalse($validator->exactLength($subject, 1));
        static::assertFalse($validator->alphaNum($subject));
        static::assertFalse($validator->name($subject));
        static::assertFalse($validator->camelName($subject));
        static::assertFalse($validator->snakeName($subject));
        static::assertFalse($validator->lispName($subject));
        static::assertFalse($validator->uuid($subject));
        static::assertFalse($validator->base64($subject));
        static::assertFalse($validator->dateDateTimeISO($subject));
        static::assertFalse($validator->dateISOLocal($subject));
        static::assertFalse($validator->timeISO($subject));
        static::assertFalse($validator->dateTimeISO($subject));
        static::assertFalse($validator->dateTimeISOLocal($subject));
        static::assertFalse($validator->dateTimeISOZonal($subject));
        static::assertFalse($validator->dateTimeISOUTC($subject));
        static::assertFalse($validator->plainText($subject));
        static::assertFalse($validator->ipAddress($subject));
        static::assertFalse($validator->url($subject));
        static::assertFalse($validator->httpUrl($subject));
        static::assertFalse($validator->email($subject));

        $subject = new Stringable();

        $subject->property = 1;
        static::assertTrue($validator->regex($subject, '/./'));
        static::assertTrue($validator->unicode($subject));
        static::assertTrue($validator->unicodePrintable($subject));
        static::assertTrue($validator->unicodeMultiLine($subject));
        static::assertTrue($validator->unicodeMinLength($subject, 1));
        static::assertTrue($validator->unicodeMaxLength($subject, 1));
        static::assertTrue($validator->unicodeExactLength($subject, 1));
        static::assertTrue($validator->hex($subject));
        static::assertTrue($validator->ascii($subject));
        static::assertTrue($validator->asciiPrintable($subject));
        static::assertTrue($validator->asciiMultiLine($subject));
        static::assertTrue($validator->minLength($subject, 1));
        static::assertTrue($validator->maxLength($subject, 1));
        static::assertTrue($validator->exactLength($subject, 1));
        static::assertTrue($validator->alphaNum($subject));

        $subject->property = 'a';
        static::assertTrue($validator->name($subject));
        static::assertTrue($validator->camelName($subject));
        static::assertTrue($validator->snakeName($subject));
        static::assertTrue($validator->lispName($subject));

        $subject->property = '5c952f47-0464-4917-b4d1-ebab14cb4fb8';
        static::assertTrue($validator->uuid($subject));

        $subject->property = base64_encode('a');
        static::assertTrue($validator->base64($subject));

        $subject->property = '2019-01-01';
        static::assertTrue($validator->dateDateTimeISO($subject));
        static::assertTrue($validator->dateISOLocal($subject));

        $subject->property = '00:00:01';
        static::assertTrue($validator->timeISO($subject));

        $subject->property = '2018-05-27T06:56:17.12345678Z';
        static::assertTrue($validator->dateTimeISO($subject));

        $subject->property = '2018-05-27 08:56:17';
        static::assertTrue($validator->dateTimeISOLocal($subject));

        $subject->property = '2018-05-27T08:56:17.123456+02:00';
        static::assertTrue($validator->dateTimeISOZonal($subject));

        $subject->property = '2018-05-27T06:56:17.12345678Z';
        static::assertTrue($validator->dateTimeISOUTC($subject));

        static::assertTrue($validator->plainText($subject));

        $subject->property = '0.0.0.0';
        static::assertTrue($validator->ipAddress($subject));

        $subject->property = 'ftp://whatever';
        static::assertTrue($validator->url($subject));

        $subject->property = 'https://whatever';
        static::assertTrue($validator->httpUrl($subject));

        $subject->property = 'a@a.a';
        static::assertTrue($validator->email($subject));

        $time = new Time();
        $dateTime = new \DateTime();
        static::assertTrue($validator->dateDateTimeISO($time));
        static::assertFalse($validator->dateDateTimeISO($dateTime));
        static::assertTrue($validator->dateTimeISOZonal($time));
        static::assertFalse($validator->dateTimeISOZonal($dateTime));

        static::assertFalse($validator->string($time));
        static::assertFalse($validator->stringableScalar($time));
        static::assertTrue($validator->stringStringable($time));
        static::assertTrue($validator->stringable($time));
        static::assertTrue($validator->anyStringable($time));
    }


    const DATE_SUBJECTS = [
        '2018-05-27' => 'ISO- date no zone',
        '2018-05-27 08:56' => 'ISO- datetime (HH:II) no zone',
        '2018-05-27 08:56:17' => 'ISO- datetime (HH:II:SS) no zone',
        '2018-05-27 08:56:17.123456' => 'ISO- datetime (HH:II:SS.micro) no zone',
        '2018-05-27 08:56:17.123456789' => 'ISO- datetime (HH:II:SS.nano) no zone',
        '2018-05-27Z' => 'ISO- date UTC',
        '2018-05-27T06:56Z' => 'ISO- datetime (HH:II) UTC',
        '2018-05-27T06:56:17Z' => 'ISO- datetime (HH:II:SS) UTC',
        '2018-05-27T06:56:17.123456Z' => 'ISO- datetime (HH:II:SS.micro) UTC',
        '2018-05-27T06:56:17.123456789Z' => 'ISO- datetime (HH:II:SS.nano) UTC',
        '2018-05-27+02:00' => 'ISO- date +02',
        '2018-05-27T08:56+02:00' => 'ISO- datetime (HH:II) +02',
        '2018-05-27T08:56:17+02:00' => 'ISO- datetime (HH:II:SS) +02',
        '2018-05-27T08:56:17.123456+02:00' => 'ISO- datetime (HH:II:SS.micro) +02',
        '2018-05-27T08:56:17.123456789+02:00' => 'ISO- datetime (HH:II:SS.nano) +02',
        '2018-05-27 00:00' => 'ISO- ambiguous datetime local or date +0 no-sign',
        '2018-05-27T06:56 00:00' => 'ISO- datetime (HH:II) +0 no-sign',
        '2018-05-27T06:56:17 00:00' => 'ISO- datetime (HH:II:SS) +0 no-sign',
        '2018-05-27T06:56:17.123456 00:00' => 'ISO- datetime (HH:II:SS.micro) +0 no-sign',
        '2018-05-27-01:30' => 'ISO- date -01:30',
        '2018-05-27T05:26-01:30' => 'ISO- datetime (HH:II) -01:30',
        '2018-05-27T05:26:17-01:30' => 'ISO- datetime (HH:II:SS) -01:30',
        '2018-05-27T05:26:17.123456-01:30' => 'ISO- datetime (HH:II:SS.micro) -01:30',
    ];

    /**
     * @see CheckedValidator::dateDateTimeISO()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateISO()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateDateTimeISO';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO- datetime (HH:II:SS.nano) no zone':
                case 'ISO- datetime (HH:II:SS.nano) UTC':
                case 'ISO- datetime (HH:II:SS.nano) +02':
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    // Inverted true/false.
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
    }

    /**
     * @see CheckedValidator::dateISOLocal()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateISOLocal()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateISOLocal';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO- date no zone':
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
    }

    /**
     * @see CheckedValidator::dateTimeISO()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateTimeISO()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateTimeISO';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO- date no zone':
                case 'ISO- datetime (HH:II) no zone':
                case 'ISO- datetime (HH:II:SS) no zone':
                case 'ISO- datetime (HH:II:SS.micro) no zone':
                case 'ISO- datetime (HH:II:SS.nano) no zone':
                case 'ISO- date UTC':
                case 'ISO- datetime (HH:II:SS.nano) UTC':
                case 'ISO- date +02':
                case 'ISO- datetime (HH:II:SS.nano) +02':
                case 'ISO- ambiguous datetime local or date +0 no-sign':
                case 'ISO- date -01:30':
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validator->{$method}($subject_by_descr['ISO- datetime (HH:II:SS.nano) UTC'], 9)
        );
        static::assertTrue(
            $validator->{$method}($subject_by_descr['ISO- datetime (HH:II:SS.nano) +02'], 9)
        );
    }

    /**
     * @see CheckedValidator::dateTimeISOLocal()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateTimeISOLocal()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateTimeISOLocal';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO- datetime (HH:II) no zone':
                case 'ISO- datetime (HH:II:SS) no zone':
                case 'ISO- ambiguous datetime local or date +0 no-sign':
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description);
            }
        }
    }

    /**
     * @see CheckedValidator::dateTimeISOZonal()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateTimeISOZonal()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateTimeISOZonal';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO- date no zone':
                case 'ISO- datetime (HH:II) no zone':
                case 'ISO- datetime (HH:II:SS) no zone':
                case 'ISO- datetime (HH:II:SS.micro) no zone':
                case 'ISO- datetime (HH:II:SS.nano) no zone':
                case 'ISO- date UTC':
                case 'ISO- datetime (HH:II) UTC':
                case 'ISO- datetime (HH:II:SS) UTC':
                case 'ISO- datetime (HH:II:SS.micro) UTC':
                case 'ISO- datetime (HH:II:SS.nano) UTC':
                case 'ISO- date +02':
                case 'ISO- datetime (HH:II:SS.nano) +02':
                case 'ISO- ambiguous datetime local or date +0 no-sign':
                case 'ISO- date -01:30':
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validator->{$method}($subject_by_descr['ISO- datetime (HH:II:SS.nano) +02'], 9)
        );
    }

    /**
     * @see CheckedValidator::dateTimeISOUTC()
     *
     * @see ValidateTest::testInstantiateCheckedValidator()
     */
    public function testDateTimeISOUTC()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $method = 'dateTimeISOUTC';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO- datetime (HH:II) UTC':
                case 'ISO- datetime (HH:II:SS) UTC':
                case 'ISO- datetime (HH:II:SS.micro) UTC':
                    static::assertTrue($validator->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertFalse($validator->{$method}($subject), $method . '(): ' . $description);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validator->{$method}($subject_by_descr['ISO- datetime (HH:II:SS.nano) UTC'], 9)
        );
    }

    public function testContainer()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $null = null;
        $bool = false;
        $array = [];
        $stdClass = new \stdClass();
        $traversable = new NoModelExplorable();
        $nonTraversable = new Stringable();

        static::assertFalse($validator->container($null));
        static::assertFalse($validator->container($bool));
        static::assertTrue($validator->container($array));
        static::assertTrue($validator->container($stdClass));
        static::assertTrue($validator->container($traversable));
        static::assertTrue($validator->container($nonTraversable));

        static::assertFalse($validator->traversable($null));
        static::assertFalse($validator->traversable($bool));
        static::assertFalse($validator->traversable($array));
        static::assertFalse($validator->traversable($stdClass));
        static::assertTrue($validator->traversable($traversable));
        static::assertFalse($validator->traversable($nonTraversable));

        static::assertFalse($validator->iterable($null));
        static::assertFalse($validator->iterable($bool));
        static::assertTrue($validator->iterable($array));
        static::assertFalse($validator->iterable($stdClass));
        static::assertTrue($validator->iterable($traversable));
        static::assertFalse($validator->iterable($nonTraversable));

        static::assertFalse($validator->loopable($null));
        static::assertFalse($validator->loopable($bool));
        static::assertTrue($validator->loopable($array));
        static::assertTrue($validator->loopable($stdClass));
        static::assertTrue($validator->loopable($traversable));
        static::assertFalse($validator->loopable($nonTraversable));

        static::assertFalse($validator->countable($null));
        static::assertFalse($validator->countable($bool));
        static::assertTrue($validator->countable($array));
        static::assertFalse($validator->countable($stdClass));
        static::assertTrue($validator->countable($traversable));
        static::assertFalse($validator->countable($nonTraversable));

        static::assertFalse($validator->sizeable($null));
        static::assertFalse($validator->sizeable($bool));
        static::assertTrue($validator->sizeable($array));
        static::assertTrue($validator->sizeable($stdClass));
        static::assertTrue($validator->sizeable($traversable));
        static::assertFalse($validator->sizeable($nonTraversable));
    }

    public function testContainerIndexedKeyed()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $array = [];
        $stdClass = new \stdClass();
        $traversable = new NoModelExplorable();
        $nonTraversable = new Stringable();

        static::assertTrue($validator->indexedArray($array));
        static::assertFalse($validator->indexedArray($stdClass));
        static::assertFalse($validator->indexedArray($traversable));
        static::assertFalse($validator->indexedArray($nonTraversable));
        static::assertTrue($validator->keyedArray($array));
        static::assertFalse($validator->keyedArray($stdClass));
        static::assertFalse($validator->keyedArray($traversable));
        static::assertFalse($validator->keyedArray($nonTraversable));

        static::assertTrue($validator->indexedIterable($array));
        static::assertFalse($validator->indexedIterable($stdClass));
        static::assertTrue($validator->indexedIterable($traversable));
        static::assertFalse($validator->indexedIterable($nonTraversable));
        static::assertTrue($validator->keyedIterable($array));
        static::assertFalse($validator->keyedIterable($stdClass));
        static::assertTrue($validator->keyedIterable($traversable));
        static::assertFalse($validator->keyedIterable($nonTraversable));

        static::assertTrue($validator->indexedLoopable($array));
        static::assertTrue($validator->indexedLoopable($stdClass));
        static::assertTrue($validator->indexedLoopable($traversable));
        static::assertFalse($validator->indexedLoopable($nonTraversable));
        static::assertTrue($validator->keyedLoopable($array));
        static::assertTrue($validator->keyedLoopable($stdClass));
        static::assertTrue($validator->keyedLoopable($traversable));
        static::assertFalse($validator->keyedLoopable($nonTraversable));


        $array[0] = 0;
        $stdClass->{'0'} = 0;
        $traversable->{'0'} = 0;
        $nonTraversable->property = 0;

        static::assertTrue($validator->indexedArray($array));
        static::assertFalse($validator->indexedArray($stdClass));
        static::assertFalse($validator->indexedArray($traversable));
        static::assertFalse($validator->indexedArray($nonTraversable));
        static::assertFalse($validator->keyedArray($array));
        static::assertFalse($validator->keyedArray($stdClass));
        static::assertFalse($validator->keyedArray($traversable));
        static::assertFalse($validator->keyedArray($nonTraversable));

        static::assertTrue($validator->indexedIterable($array));
        static::assertFalse($validator->indexedIterable($stdClass));
        static::assertTrue($validator->indexedIterable($traversable));
        static::assertFalse($validator->indexedIterable($nonTraversable));
        static::assertFalse($validator->keyedIterable($array));
        static::assertFalse($validator->keyedIterable($stdClass));
        static::assertFalse($validator->keyedIterable($traversable));
        static::assertFalse($validator->keyedIterable($nonTraversable));

        static::assertTrue($validator->indexedLoopable($array));
        static::assertTrue($validator->indexedLoopable($stdClass));
        static::assertTrue($validator->indexedLoopable($traversable));
        static::assertFalse($validator->indexedLoopable($nonTraversable));
        static::assertFalse($validator->keyedLoopable($array));
        static::assertFalse($validator->keyedLoopable($stdClass));
        static::assertFalse($validator->keyedLoopable($traversable));
        static::assertFalse($validator->keyedLoopable($nonTraversable));


        $array['one'] = 1;
        $stdClass->{'one'} = 1;
        $traversable->{'one'} = 1;

        static::assertFalse($validator->indexedArray($array));
        static::assertFalse($validator->indexedArray($stdClass));
        static::assertFalse($validator->indexedArray($traversable));
        static::assertFalse($validator->indexedArray($nonTraversable));
        static::assertTrue($validator->keyedArray($array));
        static::assertFalse($validator->keyedArray($stdClass));
        static::assertFalse($validator->keyedArray($traversable));
        static::assertFalse($validator->keyedArray($nonTraversable));

        static::assertFalse($validator->indexedIterable($array));
        static::assertFalse($validator->indexedIterable($stdClass));
        static::assertFalse($validator->indexedIterable($traversable));
        static::assertFalse($validator->indexedIterable($nonTraversable));
        static::assertTrue($validator->keyedIterable($array));
        static::assertFalse($validator->keyedIterable($stdClass));
        static::assertTrue($validator->keyedIterable($traversable));
        static::assertFalse($validator->keyedIterable($nonTraversable));

        static::assertFalse($validator->indexedLoopable($array));
        static::assertFalse($validator->indexedLoopable($stdClass));
        static::assertFalse($validator->indexedLoopable($traversable));
        static::assertFalse($validator->indexedLoopable($nonTraversable));
        static::assertTrue($validator->keyedLoopable($array));
        static::assertTrue($validator->keyedLoopable($stdClass));
        static::assertTrue($validator->keyedLoopable($traversable));
        static::assertFalse($validator->keyedLoopable($nonTraversable));
    }

    public function testContainerSize()
    {
        $validator = $this->testInstantiateCheckedValidator();

        $array = [];
        $stdClass = new \stdClass();
        $traversable = new NoModelExplorable();
        $nonTraversable = new Stringable();

        static::assertFalse($validator->minSize($array, 1));
        static::assertFalse($validator->minSize($stdClass, 1));
        static::assertFalse($validator->minSize($traversable, 1));
        static::assertFalse($validator->minSize($nonTraversable, 1));
        static::assertTrue($validator->maxSize($array, 1));
        static::assertTrue($validator->maxSize($stdClass, 1));
        static::assertTrue($validator->maxSize($traversable, 1));
        static::assertFalse($validator->maxSize($nonTraversable, 1));
        static::assertFalse($validator->exactSize($array, 1));
        static::assertFalse($validator->exactSize($stdClass, 1));
        static::assertFalse($validator->exactSize($traversable, 1));
        static::assertFalse($validator->exactSize($nonTraversable, 1));


        $array[0] = 0;
        $stdClass->{'0'} = 0;
        $traversable->{'0'} = 0;
        $nonTraversable->property = 0;

        static::assertTrue($validator->minSize($array, 1));
        static::assertTrue($validator->minSize($stdClass, 1));
        static::assertTrue($validator->minSize($traversable, 1));
        static::assertFalse($validator->minSize($nonTraversable, 1));
        static::assertTrue($validator->maxSize($array, 1));
        static::assertTrue($validator->maxSize($stdClass, 1));
        static::assertTrue($validator->maxSize($traversable, 1));
        static::assertFalse($validator->maxSize($nonTraversable, 1));
        static::assertTrue($validator->exactSize($array, 1));
        static::assertTrue($validator->exactSize($stdClass, 1));
        static::assertTrue($validator->exactSize($traversable, 1));
        static::assertFalse($validator->exactSize($nonTraversable, 1));


        $array['one'] = 1;
        $stdClass->{'one'} = 1;
        $traversable->{'one'} = 1;

        static::assertTrue($validator->minSize($array, 1));
        static::assertTrue($validator->minSize($stdClass, 1));
        static::assertTrue($validator->minSize($traversable, 1));
        static::assertFalse($validator->minSize($nonTraversable, 1));
        static::assertFalse($validator->maxSize($array, 1));
        static::assertFalse($validator->maxSize($stdClass, 1));
        static::assertFalse($validator->maxSize($traversable, 1));
        static::assertFalse($validator->maxSize($nonTraversable, 1));
        static::assertFalse($validator->exactSize($array, 1));
        static::assertFalse($validator->exactSize($stdClass, 1));
        static::assertFalse($validator->exactSize($traversable, 1));
        static::assertFalse($validator->exactSize($nonTraversable, 1));
    }
}
