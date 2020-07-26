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

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Helper\PathList;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/RuleProviderIntegrityTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class RuleProviderIntegrityTest extends TestCase
{

    protected const PATH = '../../src';

    /**
     */
    public function testAllRuleProviders()
    {

        // @todo: find all concrete rule-provider classes
        /**
         * @see RuleProviderInterface::getAPICompliance()
         */
    }

}
