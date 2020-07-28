<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Validate;

use PHPUnit\Framework\TestCase;

use SimpleComplex\Validate\Helper\Helper;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/ObjectTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class ObjectTest extends TestCase
{
    /**
     * Test that all unextended classes are \stdClass, except anonymous classes.
     */
    public function testObjectType()
    {

        static::assertSame(\stdClass::class, Helper::getType(json_decode('{}')), 'json_decode(\'{}\')');
        static::assertSame(
            \stdClass::class, Helper::getType(json_decode('{"prop":"value"}')), 'json_decode(\'{"prop":"value"}\')'
        );

        $samples = [
            'new \\stdClass()' => new \stdClass(),
            '(object) []' => (object) [],
            // Anonymous class.
            'new class{}' => new class{},
            // An extending class.
            'new Stringable()' => new Stringable(),
        ];
        foreach ($samples as $msg => $obj) {
            switch ($msg) {
                case 'new class{}':
                    $expected = 'class@anonymous';
                    break;
                case 'new Stringabe()':
                    $expected = 'SimpleComplex\\T';
                    break;
                default:
                    $expected = \stdClass::class;
            }
            static::assertSame(
                $expected,
                substr(Helper::getType($obj), 0, 15),
                $msg
            );
            if ($msg != 'new class{}') {
                static::assertSame(
                    $expected,
                    substr(Helper::getType(unserialize(serialize($obj))), 0, 15),
                    'unserialize(serialize(' . $msg . '))'
                );
            }

        }
    }
}
