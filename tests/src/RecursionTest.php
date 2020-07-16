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

use SimpleComplex\Validate\Validate;
use SimpleComplex\Validate\ValidationRuleSet;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/RecursionTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class RecursionTest extends TestCase
{
    /**
     * @return Validate
     */
    public function testInstantiation()
    {
        $validate = new Validate();
        static::assertInstanceOf(Validate::class, $validate);
        return $validate;
    }


    public function testRuleSet()
    {
        $validate = $this->testInstantiation();

        $source = BicycleRuleSets::original();

        $ruleSet = new ValidationRuleSet($source, $validate);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet);
        \SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();
    }
}
