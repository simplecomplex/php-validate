<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Validate;

use PHPUnit\Framework\TestCase;
use SimpleComplex\Tests\Utils\BootstrapTest;

use SimpleComplex\Validate\Validate;
use SimpleComplex\Validate\ValidationRuleSet;

/**
 * @code
 * // CLI, in document root:
 * backend/vendor/bin/phpunit backend/vendor/simplecomplex/validate/tests/src/ValidateTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class ValidateTest extends TestCase
{
    /**
     * @see BootstrapTest::testDependencies()
     *
     * @return Validate
     */
    public function testInstantiation()
    {
        $validate = (new BootstrapTest())->testDependencies()->get('validate');
        static::assertInstanceOf(Validate::class, $validate);
        return $validate;
    }

    /**
     * @see Validate::empty()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testEmpty()
    {
        $validate = $this->testInstantiation();

        static::assertTrue($validate->empty(null));

        static::assertTrue($validate->empty(false));
        static::assertFalse($validate->empty(true));

        static::assertTrue($validate->empty(0));
        static::assertFalse($validate->empty(1));

        static::assertTrue($validate->empty(''));
        static::assertFalse($validate->empty(' '));
        static::assertFalse($validate->empty('0'));

        static::assertTrue($validate->empty([]));
        static::assertFalse($validate->empty([0]));

        $o = new \stdClass();
        static::assertTrue($validate->empty($o));
        $o->a = 0;
        static::assertFalse($validate->empty($o));

        $o = new \ArrayObject();
        static::assertTrue($validate->empty($o));
        $o[0] = 0;
        static::assertFalse($validate->empty($o));

        $o = new EmptyExplorable();
        static::assertTrue($validate->empty($o));
        $o = new NonEmptyExplorable();
        static::assertFalse($validate->empty($o));
    }

    /**
     * @see Validate::nonEmpty()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testNonEmpty()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->nonEmpty(null));

        static::assertFalse($validate->nonEmpty(false));
        static::assertTrue($validate->nonEmpty(true));

        static::assertFalse($validate->nonEmpty(0));
        static::assertTrue($validate->nonEmpty(1));

        static::assertFalse($validate->nonEmpty(''));
        static::assertTrue($validate->nonEmpty(' '));
        static::assertTrue($validate->nonEmpty('0'));

        static::assertFalse($validate->nonEmpty([]));
        static::assertTrue($validate->nonEmpty([0]));

        $o = new \stdClass();
        static::assertFalse($validate->nonEmpty($o));
        $o->a = 0;
        static::assertTrue($validate->nonEmpty($o));

        $o = new \ArrayObject();
        static::assertFalse($validate->nonEmpty($o));
        $o[0] = 0;
        static::assertTrue($validate->nonEmpty($o));

        $o = new EmptyExplorable();
        static::assertFalse($validate->nonEmpty($o));
        $o = new NonEmptyExplorable();
        static::assertTrue($validate->nonEmpty($o));
    }

    public function testNull()
    {
        $validate = $this->testInstantiation();

        // Non-parameterized rule methods.
        $simple_rule_methods = ValidationRuleSet::ruleMethodsAvailable($validate);
        $parameterized_methods = $validate->getParameterMethods();
        $simple_rule_methods =  array_diff($simple_rule_methods, array_keys($parameterized_methods));
        foreach ($parameterized_methods as $rule => $required) {
            if (!$required) {
                $simple_rule_methods[] = $rule;
            }
        }

        foreach ($simple_rule_methods as $rule) {
            switch ($rule) {
                case 'empty':
                case 'null':
                    static::assertTrue($validate->{$rule}(null), 'Rule method (true): ' . $rule);
                    break;
                default:
                    static::assertFalse($validate->{$rule}(null), 'Rule method (false): ' . $rule);
            }
        }

        /**
        'enum' => true,
        'regex' => true,
        'class' => true,
        'hex' => false,
        'min' => true,
        'max' => true,
        'range' => true,
        'unicodeMultiLine' => false,
        'unicodeMinLength' => true,
        'unicodeMaxLength' => true,
        'unicodeExactLength' => true,
        //'asciiMultiLine' => false,
        'minLength' => true,
        'maxLength' => true,
        'exactLength' => true,
        'alphaNum' => false,
        'name' => false,
        'snakeName' => false,
        'lispName' => false,
        'uuid' => false,
        'timeISO8601' => false,
        'dateTimeISO8601' => false,
        'dateTimeISO8601Zonal' => false,
        'dateTimeISOUTC' => false,
         */

        // enum.
        static::assertFalse($validate->enum(null, [1]), 'Rule method (false): ' . 'enum');
        static::assertTrue($validate->enum(null, [1, null]), 'Rule method (true): ' . 'enum');


        static::assertFalse($validate->regex(null, '/./'), 'Rule method (false): ' . 'regex');
        static::assertFalse($validate->class(null, \stdClass::class), 'Rule method (false): ' . 'class');

        static::assertFalse($validate->min(null, 0), 'Rule method (false): ' . 'min');
        static::assertFalse($validate->max(null, 0), 'Rule method (false): ' . 'max');
        static::assertFalse($validate->range(null, 0, 1), 'Rule method (false): ' . 'range');

        static::assertFalse($validate->unicodeMinLength(null, 0), 'Rule method (false): ' . 'unicodeMinLength');
        static::assertFalse($validate->unicodeMaxLength(null, 0), 'Rule method (false): ' . 'unicodeMaxLength');
        static::assertFalse($validate->unicodeExactLength(null, 0), 'Rule method (false): ' . 'unicodeExactLength');
    }


    public function testAllowNull()
    {
        $validate = $this->testInstantiation();

        $ruleSet = new ValidationRuleSet([
            'nonNegative' => true,
        ]);
        static::assertFalse($validate->challenge(null, $ruleSet), 'Rule method (false): ' . 'nonNegative');
        $ruleSet = new ValidationRuleSet([
            'nonNegative' => true,
            'allowNull' => true,
        ]);
        static::assertTrue($validate->challenge(null, $ruleSet), 'Rule method (true): ' . 'nonNegative');
    }

    /**
     * @see Validate::enum()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testEnum()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->enum([], [[]]));
        $o = new \stdClass();
        static::assertFalse($validate->enum($o, [$o]));

        static::assertFalse($validate->enum(null, [0]));
        static::assertFalse($validate->enum(false, [0]));
        static::assertFalse($validate->enum(true, [0]));

        static::assertTrue($validate->enum(null, [null, false, true]));
        static::assertTrue($validate->enum(false, [null, false, true]));
        static::assertTrue($validate->enum(true, [null, false, true]));

        static::assertTrue($validate->enum(0, [0]));
        static::assertFalse($validate->enum('0', [0]));
    }

    /**
     * @see Validate::regex()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testRegex()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->regex(null, '/0/'));

        static::assertTrue($validate->regex(false, '//'));
        static::assertTrue($validate->regex(true, '/1/'));

        static::assertTrue($validate->regex('a', '/a/'));
        static::assertFalse($validate->regex('a', '/b/'));
        static::assertFalse($validate->regex('a', '/\d/'));
    }

    /**
     * @see Validate::boolean()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testBoolean()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->boolean(null));
        static::assertTrue($validate->boolean(false));
        static::assertTrue($validate->boolean(true));

        static::assertFalse($validate->boolean(0));
        static::assertFalse($validate->boolean('0'));
        static::assertFalse($validate->boolean('a'));
    }

    /**
     * @see Validate::number()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testNumber()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->number(null));
        static::assertFalse($validate->number(false));
        static::assertFalse($validate->number(true));

        static::assertSame('integer', $validate->number(0));
        static::assertSame('float', $validate->number(0.0));
        static::assertSame('integer', $validate->number(1));
        static::assertSame('float', $validate->number(1.0));
        static::assertSame('integer', $validate->number(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertSame('float', $validate->number(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validate->number('0'));
        static::assertFalse($validate->number('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validate->number($o));
        static::assertFalse($validate->number('' . $o));
    }

    /**
     * @see Validate::integer()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testInteger()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->integer(null));
        static::assertFalse($validate->integer(false));
        static::assertFalse($validate->integer(true));

        static::assertTrue($validate->integer(0));
        static::assertFalse($validate->integer(0.0));
        static::assertTrue($validate->integer(1));
        static::assertFalse($validate->integer(1.0));
        static::assertTrue($validate->integer(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertFalse($validate->integer(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validate->integer('0'));
        static::assertFalse($validate->integer('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validate->integer($o));
        static::assertFalse($validate->integer('' . $o));
    }

    /**
     * @see Validate::float()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testFloat()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->float(null));
        static::assertFalse($validate->float(false));
        static::assertFalse($validate->float(true));

        static::assertFalse($validate->float(0));
        static::assertTrue($validate->float(0.0));
        static::assertFalse($validate->float(1));
        static::assertTrue($validate->float(1.0));
        static::assertFalse($validate->float(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertTrue($validate->float(constant('PHP_FLOAT_MAX')));
        }
        static::assertFalse($validate->float('0'));
        static::assertFalse($validate->float('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validate->float($o));
        static::assertFalse($validate->float('' . $o));
    }

    /**
     * @see Validate::string()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testString()
    {
        $validate = $this->testInstantiation();

        static::assertFalse($validate->string(null));
        static::assertFalse($validate->string(false));
        static::assertFalse($validate->string(true));

        static::assertFalse($validate->string(0));
        static::assertFalse($validate->string(0.0));
        static::assertFalse($validate->string(1));
        static::assertFalse($validate->string(1.0));
        static::assertFalse($validate->string(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            static::assertFalse($validate->string(constant('PHP_FLOAT_MAX')));
        }
        static::assertTrue($validate->string('0'));
        static::assertTrue($validate->string('a'));
        $o = new Stringable();
        $o->property = 0;
        static::assertFalse($validate->string($o));
        static::assertTrue($validate->string('' . $o));
    }

    /**
     * @see Validate::dateISO8601()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testSubjectStringCoercion()
    {
        $validate = $this->testInstantiation();

        $subject = new \stdClass();

        static::assertFalse($validate->regex($subject, '/./'));
        static::assertFalse($validate->unicode($subject));
        static::assertFalse($validate->unicodePrintable($subject));
        static::assertFalse($validate->unicodeMultiLine($subject));
        static::assertFalse($validate->unicodeMinLength($subject, 1));
        static::assertFalse($validate->unicodeMaxLength($subject, 1));
        static::assertFalse($validate->unicodeExactLength($subject, 1));
        static::assertFalse($validate->hex($subject));
        static::assertFalse($validate->ascii($subject));
        static::assertFalse($validate->asciiPrintable($subject));
        static::assertFalse($validate->asciiMultiLine($subject));
        static::assertFalse($validate->minLength($subject, 1));
        static::assertFalse($validate->maxLength($subject, 1));
        static::assertFalse($validate->exactLength($subject, 1));
        static::assertFalse($validate->alphaNum($subject));
        static::assertFalse($validate->name($subject));
        static::assertFalse($validate->camelName($subject));
        static::assertFalse($validate->snakeName($subject));
        static::assertFalse($validate->lispName($subject));
        static::assertFalse($validate->uuid($subject));
        static::assertFalse($validate->base64($subject));
        static::assertFalse($validate->dateISO8601($subject));
        static::assertFalse($validate->dateISO8601Local($subject));
        static::assertFalse($validate->timeISO8601($subject));
        static::assertFalse($validate->dateTimeISO8601($subject));
        static::assertFalse($validate->dateTimeISO8601Local($subject));
        static::assertFalse($validate->dateTimeISO8601Zonal($subject));
        static::assertFalse($validate->dateTimeISOUTC($subject));
        static::assertFalse($validate->plainText($subject));
        static::assertFalse($validate->ipAddress($subject));
        static::assertFalse($validate->url($subject));
        static::assertFalse($validate->httpUrl($subject));
        static::assertFalse($validate->email($subject));

        $subject = new Stringable();

        $subject->property = 1;
        static::assertTrue($validate->regex($subject, '/./'));
        static::assertTrue($validate->unicode($subject));
        static::assertTrue($validate->unicodePrintable($subject));
        static::assertTrue($validate->unicodeMultiLine($subject));
        static::assertTrue($validate->unicodeMinLength($subject, 1));
        static::assertTrue($validate->unicodeMaxLength($subject, 1));
        static::assertTrue($validate->unicodeExactLength($subject, 1));
        static::assertTrue($validate->hex($subject));
        static::assertTrue($validate->ascii($subject));
        static::assertTrue($validate->asciiPrintable($subject));
        static::assertTrue($validate->asciiMultiLine($subject));
        static::assertTrue($validate->minLength($subject, 1));
        static::assertTrue($validate->maxLength($subject, 1));
        static::assertTrue($validate->exactLength($subject, 1));
        static::assertTrue($validate->alphaNum($subject));

        $subject->property = 'a';
        static::assertTrue($validate->name($subject));
        static::assertTrue($validate->camelName($subject));
        static::assertTrue($validate->snakeName($subject));
        static::assertTrue($validate->lispName($subject));

        $subject->property = '5c952f47-0464-4917-b4d1-ebab14cb4fb8';
        static::assertTrue($validate->uuid($subject));

        $subject->property = base64_encode('a');
        static::assertTrue($validate->base64($subject));

        $subject->property = '2019-01-01';
        static::assertTrue($validate->dateISO8601($subject));
        static::assertTrue($validate->dateISO8601Local($subject));

        $subject->property = '00:00:01';
        static::assertTrue($validate->timeISO8601($subject));

        $subject->property = '2018-05-27T06:56:17.12345678Z';
        static::assertTrue($validate->dateTimeISO8601($subject));

        $subject->property = '2018-05-27 08:56:17';
        static::assertTrue($validate->dateTimeISO8601Local($subject));

        $subject->property = '2018-05-27T08:56:17.123456+02:00';
        static::assertTrue($validate->dateTimeISO8601Zonal($subject));

        $subject->property = '2018-05-27T06:56:17.12345678Z';
        static::assertTrue($validate->dateTimeISOUTC($subject));

        static::assertTrue($validate->plainText($subject));

        $subject->property = '0.0.0.0';
        static::assertTrue($validate->ipAddress($subject));

        $subject->property = 'ftp://whatever';
        static::assertTrue($validate->url($subject));

        $subject->property = 'https://whatever';
        static::assertTrue($validate->httpUrl($subject));

        $subject->property = 'a@a.a';
        static::assertTrue($validate->email($subject));
    }


    const DATE_SUBJECTS = [
        '2018-05-27' => 'ISO-8601 date no zone',
        '2018-05-27 08:56' => 'ISO-8601 datetime (HH:II) no zone',
        '2018-05-27 08:56:17' => 'ISO-8601 datetime (HH:II:SS) no zone',
        '2018-05-27 08:56:17.123456' => 'ISO-8601 datetime (HH:II:SS.micro) no zone',
        '2018-05-27 08:56:17.123456789' => 'ISO-8601 datetime (HH:II:SS.nano) no zone',
        '2018-05-27Z' => 'ISO-8601 date UTC',
        '2018-05-27T06:56Z' => 'ISO-8601 datetime (HH:II) UTC',
        '2018-05-27T06:56:17Z' => 'ISO-8601 datetime (HH:II:SS) UTC',
        '2018-05-27T06:56:17.123456Z' => 'ISO-8601 datetime (HH:II:SS.micro) UTC',
        '2018-05-27T06:56:17.123456789Z' => 'ISO-8601 datetime (HH:II:SS.nano) UTC',
        '2018-05-27+02:00' => 'ISO-8601 date +02',
        '2018-05-27T08:56+02:00' => 'ISO-8601 datetime (HH:II) +02',
        '2018-05-27T08:56:17+02:00' => 'ISO-8601 datetime (HH:II:SS) +02',
        '2018-05-27T08:56:17.123456+02:00' => 'ISO-8601 datetime (HH:II:SS.micro) +02',
        '2018-05-27T08:56:17.123456789+02:00' => 'ISO-8601 datetime (HH:II:SS.nano) +02',
        '2018-05-27 00:00' => 'ISO-8601 ambiguous datetime local or date +0 no-sign',
        '2018-05-27T06:56 00:00' => 'ISO-8601 datetime (HH:II) +0 no-sign',
        '2018-05-27T06:56:17 00:00' => 'ISO-8601 datetime (HH:II:SS) +0 no-sign',
        '2018-05-27T06:56:17.123456 00:00' => 'ISO-8601 datetime (HH:II:SS.micro) +0 no-sign',
        '2018-05-27-01:30' => 'ISO-8601 date -01:30',
        '2018-05-27T05:26-01:30' => 'ISO-8601 datetime (HH:II) -01:30',
        '2018-05-27T05:26:17-01:30' => 'ISO-8601 datetime (HH:II:SS) -01:30',
        '2018-05-27T05:26:17.123456-01:30' => 'ISO-8601 datetime (HH:II:SS.micro) -01:30',
    ];

    /**
     * @see Validate::dateISO8601()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateISO8601()
    {
        $validate = $this->testInstantiation();

        $method = 'dateISO8601';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO-8601 datetime (HH:II:SS.nano) no zone':
                case 'ISO-8601 datetime (HH:II:SS.nano) UTC':
                case 'ISO-8601 datetime (HH:II:SS.nano) +02':
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    // Inverted true/false.
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
    }

    /**
     * @see Validate::dateISO8601Local()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateISO8601Local()
    {
        $validate = $this->testInstantiation();

        $method = 'dateISO8601Local';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO-8601 date no zone':
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
    }

    /**
     * @see Validate::dateTimeISO8601()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateTimeISO8601()
    {
        $validate = $this->testInstantiation();

        $method = 'dateTimeISO8601';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO-8601 date no zone':
                case 'ISO-8601 datetime (HH:II) no zone':
                case 'ISO-8601 datetime (HH:II:SS) no zone':
                case 'ISO-8601 datetime (HH:II:SS.micro) no zone':
                case 'ISO-8601 datetime (HH:II:SS.nano) no zone':
                case 'ISO-8601 date UTC':
                case 'ISO-8601 datetime (HH:II:SS.nano) UTC':
                case 'ISO-8601 date +02':
                case 'ISO-8601 datetime (HH:II:SS.nano) +02':
                case 'ISO-8601 ambiguous datetime local or date +0 no-sign':
                case 'ISO-8601 date -01:30':
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
                    break;
                default:
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description . ' - ' . $subject);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validate->{$method}($subject_by_descr['ISO-8601 datetime (HH:II:SS.nano) UTC'], 9)
        );
        static::assertTrue(
            $validate->{$method}($subject_by_descr['ISO-8601 datetime (HH:II:SS.nano) +02'], 9)
        );
    }

    /**
     * @see Validate::dateTimeISO8601Local()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateTimeISO8601Local()
    {
        $validate = $this->testInstantiation();

        $method = 'dateTimeISO8601Local';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO-8601 datetime (HH:II) no zone':
                case 'ISO-8601 datetime (HH:II:SS) no zone':
                case 'ISO-8601 ambiguous datetime local or date +0 no-sign':
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
    }

    /**
     * @see Validate::dateTimeISO8601Zonal()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateTimeISO8601Zonal()
    {
        $validate = $this->testInstantiation();

        $method = 'dateTimeISO8601Zonal';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                case 'ISO-8601 date no zone':
                case 'ISO-8601 datetime (HH:II) no zone':
                case 'ISO-8601 datetime (HH:II:SS) no zone':
                case 'ISO-8601 datetime (HH:II:SS.micro) no zone':
                case 'ISO-8601 datetime (HH:II:SS.nano) no zone':
                case 'ISO-8601 date UTC':
                case 'ISO-8601 datetime (HH:II) UTC':
                case 'ISO-8601 datetime (HH:II:SS) UTC':
                case 'ISO-8601 datetime (HH:II:SS.micro) UTC':
                case 'ISO-8601 datetime (HH:II:SS.nano) UTC':
                case 'ISO-8601 date +02':
                case 'ISO-8601 datetime (HH:II:SS.nano) +02':
                case 'ISO-8601 ambiguous datetime local or date +0 no-sign':
                case 'ISO-8601 date -01:30':
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validate->{$method}($subject_by_descr['ISO-8601 datetime (HH:II:SS.nano) +02'], 9)
        );
    }

    /**
     * @see Validate::dateTimeISOUTC()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testDateTimeISOUTC()
    {
        $validate = $this->testInstantiation();

        $method = 'dateTimeISOUTC';

        foreach (static::DATE_SUBJECTS as $subject => $description) {
            switch ($description) {
                // Inverted true/false.
                case 'ISO-8601 datetime (HH:II) UTC':
                case 'ISO-8601 datetime (HH:II:SS) UTC':
                case 'ISO-8601 datetime (HH:II:SS.micro) UTC':
                    static::assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    static::assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
        $subject_by_descr = array_flip(static::DATE_SUBJECTS);
        static::assertTrue(
            $validate->{$method}($subject_by_descr['ISO-8601 datetime (HH:II:SS.nano) UTC'], 9)
        );
    }
}
