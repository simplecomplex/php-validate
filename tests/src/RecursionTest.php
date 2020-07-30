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

use SimpleComplex\Validate\Exception\ValidationException;
use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\AbstractValidator;
use SimpleComplex\Validate\Interfaces\ChallengerInterface;
use SimpleComplex\Validate\UncheckedValidator;
use SimpleComplex\Validate\Validator;

use SimpleComplex\Validate\RuleSet\ValidationRuleSet;
use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;

use SimpleComplex\Tests\Validate\Entity\Bicycle;
use SimpleComplex\Tests\Validate\RuleSetPHP\BicycleRuleSets;

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
    protected const RULESET_JSON_PATH = '/RuleSetJSON';

    protected const CHALLENGE_MODE = ChallengerInterface::RECORD | ChallengerInterface::CONTINUE;

    /**
     * @return AbstractValidator
     */
    public function testInstantiateUncheckedValidator()
    {
        $validator = UncheckedValidator::getInstance();
        static::assertInstanceOf(UncheckedValidator::class, $validator);
        return $validator;
    }

    /**
     * @return AbstractValidator
     */
    public function testInstantiateValidator()
    {
        $validator = Validator::getInstance();
        static::assertInstanceOf(Validator::class, $validator);
        return $validator;
    }

    protected static function getRuleSetJSON(string $name)
    {
        $file = realpath(dirname(__FILE__) . '/' . static::RULESET_JSON_PATH) . '/' . $name . '.json';
        static::assertFileExists($file, dirname(__FILE__) . '/' . static::RULESET_JSON_PATH . '/' . $name . '.json');

        $json = file_get_contents($file);
        static::assertIsString($json, $file);
        static::assertNotEmpty($json, $file);
        return $json;
    }

    /**
     * @throws ValidationException
     */
    public function testRuleSetAddress()
    {
        $validator = $this->testInstantiateUncheckedValidator();
        $factory = new RuleSetFactory($validator);

        $source_address = Helper::parseJsonString(static::getRuleSetJSON('Address'));
        static::assertIsObject($source_address);

        $ruleSet_address = $factory->make($source_address);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_address);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_address)->log();

        $address = [
            'streetName' => 'Any Street',
            'streetBuilding' => '7A',
            //'streetBuilding' => 7,
            //'streetBuilding' => [],
            'municipalityName' => 'Any Municipality',
        ];

        $valid = $validator->challenge($address, $ruleSet_address, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log("\n" . $validator->getLastFailure() . "\n");
        }
        static::assertTrue($valid);

        $source_person = Helper::parseJsonString(static::getRuleSetJSON('Person'));
        static::assertIsObject($source_person);
        //
        $source_person->tableElements->address = $source_address;

        $ruleSet_person = $factory->make($source_person);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_person);
        \SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_person)->log();

    }

}
