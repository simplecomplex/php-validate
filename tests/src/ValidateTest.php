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
        $this->assertInstanceOf(Validate::class, $validate);
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

        $this->assertTrue($validate->empty(null));

        $this->assertTrue($validate->empty(false));
        $this->assertFalse($validate->empty(true));

        $this->assertTrue($validate->empty(0));
        $this->assertFalse($validate->empty(1));

        $this->assertTrue($validate->empty(''));
        $this->assertFalse($validate->empty(' '));
        $this->assertFalse($validate->empty('0'));

        $this->assertTrue($validate->empty([]));
        $this->assertFalse($validate->empty([0]));

        $o = new \stdClass();
        $this->assertTrue($validate->empty($o));
        $o->a = 0;
        $this->assertFalse($validate->empty($o));

        $o = new \ArrayObject();
        $this->assertTrue($validate->empty($o));
        $o[0] = 0;
        $this->assertFalse($validate->empty($o));

        $o = new EmptyExplorable();
        $this->assertTrue($validate->empty($o));
        $o = new NonEmptyExplorable();
        $this->assertFalse($validate->empty($o));
    }

    /**
     * @see Validate::nonEmpty()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testNonEmpty()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->nonEmpty(null));

        $this->assertFalse($validate->nonEmpty(false));
        $this->assertTrue($validate->nonEmpty(true));

        $this->assertFalse($validate->nonEmpty(0));
        $this->assertTrue($validate->nonEmpty(1));

        $this->assertFalse($validate->nonEmpty(''));
        $this->assertTrue($validate->nonEmpty(' '));
        $this->assertTrue($validate->nonEmpty('0'));

        $this->assertFalse($validate->nonEmpty([]));
        $this->assertTrue($validate->nonEmpty([0]));

        $o = new \stdClass();
        $this->assertFalse($validate->nonEmpty($o));
        $o->a = 0;
        $this->assertTrue($validate->nonEmpty($o));

        $o = new \ArrayObject();
        $this->assertFalse($validate->nonEmpty($o));
        $o[0] = 0;
        $this->assertTrue($validate->nonEmpty($o));

        $o = new EmptyExplorable();
        $this->assertFalse($validate->nonEmpty($o));
        $o = new NonEmptyExplorable();
        $this->assertTrue($validate->nonEmpty($o));
    }

    /**
     * @see Validate::enum()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testEnum()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->enum([], [[]]));
        $o = new \stdClass();
        $this->assertFalse($validate->enum($o, [$o]));

        $this->assertFalse($validate->enum(null, [0]));
        $this->assertFalse($validate->enum(false, [0]));
        $this->assertFalse($validate->enum(true, [0]));

        $this->assertTrue($validate->enum(null, [null, false, true]));
        $this->assertTrue($validate->enum(false, [null, false, true]));
        $this->assertTrue($validate->enum(true, [null, false, true]));

        $this->assertTrue($validate->enum(0, [0]));
        $this->assertFalse($validate->enum('0', [0]));
    }

    /**
     * @see Validate::regex()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testRegex()
    {
        $validate = $this->testInstantiation();

        $this->assertTrue($validate->regex(null, '//'));
        $this->assertTrue($validate->regex(false, '//'));
        $this->assertTrue($validate->regex(true, '/1/'));

        $this->assertTrue($validate->regex('a', '/a/'));
        $this->assertFalse($validate->regex('a', '/b/'));
        $this->assertFalse($validate->regex('a', '/\d/'));
    }

    /**
     * @see Validate::boolean()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testBoolean()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->boolean(null));
        $this->assertTrue($validate->boolean(false));
        $this->assertTrue($validate->boolean(true));

        $this->assertFalse($validate->boolean(0));
        $this->assertFalse($validate->boolean('0'));
        $this->assertFalse($validate->boolean('a'));
    }

    /**
     * @see Validate::number()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testNumber()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->number(null));
        $this->assertFalse($validate->number(false));
        $this->assertFalse($validate->number(true));

        $this->assertSame('integer', $validate->number(0));
        $this->assertSame('float', $validate->number(0.0));
        $this->assertSame('integer', $validate->number(1));
        $this->assertSame('float', $validate->number(1.0));
        $this->assertSame('integer', $validate->number(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            $this->assertSame('float', $validate->number(constant('PHP_FLOAT_MAX')));
        }
        $this->assertFalse($validate->number('0'));
        $this->assertFalse($validate->number('a'));
        $o = new Stringable();
        $o->property = 0;
        $this->assertFalse($validate->number($o));
        $this->assertFalse($validate->number('' . $o));
    }

    /**
     * @see Validate::integer()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testInteger()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->integer(null));
        $this->assertFalse($validate->integer(false));
        $this->assertFalse($validate->integer(true));

        $this->assertTrue($validate->integer(0));
        $this->assertFalse($validate->integer(0.0));
        $this->assertTrue($validate->integer(1));
        $this->assertFalse($validate->integer(1.0));
        $this->assertTrue($validate->integer(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            $this->assertFalse($validate->integer(constant('PHP_FLOAT_MAX')));
        }
        $this->assertFalse($validate->integer('0'));
        $this->assertFalse($validate->integer('a'));
        $o = new Stringable();
        $o->property = 0;
        $this->assertFalse($validate->integer($o));
        $this->assertFalse($validate->integer('' . $o));
    }

    /**
     * @see Validate::float()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testFloat()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->float(null));
        $this->assertFalse($validate->float(false));
        $this->assertFalse($validate->float(true));

        $this->assertFalse($validate->float(0));
        $this->assertTrue($validate->float(0.0));
        $this->assertFalse($validate->float(1));
        $this->assertTrue($validate->float(1.0));
        $this->assertFalse($validate->float(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            $this->assertTrue($validate->float(constant('PHP_FLOAT_MAX')));
        }
        $this->assertFalse($validate->float('0'));
        $this->assertFalse($validate->float('a'));
        $o = new Stringable();
        $o->property = 0;
        $this->assertFalse($validate->float($o));
        $this->assertFalse($validate->float('' . $o));
    }

    /**
     * @see Validate::string()
     *
     * @see ValidateTest::testInstantiation()
     */
    public function testString()
    {
        $validate = $this->testInstantiation();

        $this->assertFalse($validate->string(null));
        $this->assertFalse($validate->string(false));
        $this->assertFalse($validate->string(true));

        $this->assertFalse($validate->string(0));
        $this->assertFalse($validate->string(0.0));
        $this->assertFalse($validate->string(1));
        $this->assertFalse($validate->string(1.0));
        $this->assertFalse($validate->string(PHP_INT_MAX));
        if (defined('PHP_FLOAT_MAX')) {
            $this->assertFalse($validate->string(constant('PHP_FLOAT_MAX')));
        }
        $this->assertTrue($validate->string('0'));
        $this->assertTrue($validate->string('a'));
        $o = new Stringable();
        $o->property = 0;
        $this->assertFalse($validate->string($o));
        $this->assertTrue($validate->string('' . $o));
    }


    const DATE_SUBJECTS = [
        '2018-05-27' => 'ISO-8601 date no zone',
        '2018-05-27 08:56' => 'ISO-8601 datetime (HH:II) no zone',
        '2018-05-27 08:56:17' => 'ISO-8601 datetime (HH:II:SS) no zone',
        '2018-05-27 08:56:17.123456' => 'ISO-8601 datetime (HH:II:SS.mmmmmm) no zone',
        '2018-05-27Z' => 'ISO-8601 date UTC',
        '2018-05-27T06:56Z' => 'ISO-8601 datetime (HH:II) UTC',
        '2018-05-27T06:56:17Z' => 'ISO-8601 datetime (HH:II:SS) UTC',
        '2018-05-27T06:56:17.123456Z' => 'ISO-8601 datetime (HH:II:SS.mmmmmm) UTC',
        '2018-05-27+02:00' => 'ISO-8601 date +02',
        '2018-05-27T08:56+02:00' => 'ISO-8601 datetime (HH:II) +02',
        '2018-05-27T08:56:17+02:00' => 'ISO-8601 datetime (HH:II:SS) +02',
        '2018-05-27T08:56:17.123456+02:00' => 'ISO-8601 datetime (HH:II:SS.mmmmmm) +02',
        '2018-05-27 00:00' => 'ISO-8601 ambiguous datetime local or date +0 no-sign',
        '2018-05-27T06:56 00:00' => 'ISO-8601 datetime (HH:II) +0 no-sign',
        '2018-05-27T06:56:17 00:00' => 'ISO-8601 datetime (HH:II:SS) +0 no-sign',
        '2018-05-27T06:56:17.123456 00:00' => 'ISO-8601 datetime (HH:II:SS.mmmmmm) +0 no-sign',
        '2018-05-27-01:30' => 'ISO-8601 date -01:30',
        '2018-05-27T05:26-01:30' => 'ISO-8601 datetime (HH:II) -01:30',
        '2018-05-27T05:26:17-01:30' => 'ISO-8601 datetime (HH:II:SS) -01:30',
        '2018-05-27T05:26:17.123456-01:30' => 'ISO-8601 datetime (HH:II:SS.mmmmmm) -01:30',
    ];

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
                    $this->assertTrue($validate->{$method}($subject));
                    break;
                default:
                    $this->assertFalse($validate->{$method}($subject));
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
                case 'ISO-8601 datetime (HH:II:SS.mmmmmm) no zone':
                case 'ISO-8601 date UTC':
                case 'ISO-8601 date +02':
                case 'ISO-8601 ambiguous datetime local or date +0 no-sign':
                case 'ISO-8601 date -01:30':
                    $this->assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    $this->assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
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
                    $this->assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    $this->assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
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
                case 'ISO-8601 datetime (HH:II:SS.mmmmmm) no zone':
                case 'ISO-8601 date UTC':
                case 'ISO-8601 datetime (HH:II) UTC':
                case 'ISO-8601 datetime (HH:II:SS) UTC':
                case 'ISO-8601 datetime (HH:II:SS.mmmmmm) UTC':
                case 'ISO-8601 date +02':
                case 'ISO-8601 ambiguous datetime local or date +0 no-sign':
                case 'ISO-8601 date -01:30':
                    $this->assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    $this->assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
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
                case 'ISO-8601 datetime (HH:II:SS.mmmmmm) UTC':
                    $this->assertTrue($validate->{$method}($subject), $method . '(): ' . $description);
                    break;
                default:
                    $this->assertFalse($validate->{$method}($subject), $method . '(): ' . $description);
            }
        }
    }
}
