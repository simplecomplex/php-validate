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
use SimpleComplex\Tests\Validate\Entity\Stringable;

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
     * Test that all unextended entities are \stdClass, except anonymous entity.
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
                case 'new Stringable()':
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

    /**
     * Test that custom and built-in classes don't extend \stdClass,
     * nor extends a (PHP nonexistent) Object base class.
     */
    public function testThereIsNoBaseClass()
    {
        $lineage = Helper::getClassLineage(new Stringable());
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($lineage)->log();
        static::assertIsArray($lineage);
        static::assertNotEmpty($lineage);
        static::assertSame(1, count($lineage));
        static::assertSame(Stringable::class, $lineage[0]);

        $lineage = Helper::getClassLineage(new \ArrayObject());
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($lineage)->log();
        static::assertIsArray($lineage);
        static::assertNotEmpty($lineage);
        static::assertSame(1, count($lineage));
        static::assertSame(\ArrayObject::class, $lineage[0]);
    }
}
