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

use SimpleComplex\Validate\Interfaces\RecursiveValidatorInterface;
use SimpleComplex\Validate\UncheckedValidator;
use SimpleComplex\Validate\CheckedValidator;

use SimpleComplex\Validate\RuleSet\ValidationRuleSet;
use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;

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

    protected const CHALLENGE_MODE = RecursiveValidatorInterface::RECORD | RecursiveValidatorInterface::CONTINUE;

    /**
     * @return UncheckedValidator
     */
    public function testInstantiateUncheckedValidator()
    {
        $validator = UncheckedValidator::getInstance();
        static::assertInstanceOf(UncheckedValidator::class, $validator);
        return $validator;
    }

    /**
     * @return UncheckedValidator
     */
    public function testInstantiateValidator()
    {
        $validator = CheckedValidator::getInstance();
        static::assertInstanceOf(CheckedValidator::class, $validator);
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

    protected function sampleAddress()
    {
        return [
            'streetName' => 'Any Street',
            'streetBuilding' => '7A',
            'municipalityName' => 'Any Municipality',
        ];
    }

    protected function samplePerson()
    {
        return [
            'personName' => 'Firstname Surname',
            'personAddress' => $this->sampleAddress(),
        ];
    }

    protected function sampleBusiness(int $numberOfOwners = 0)
    {
        $business = [
            'businessName' => 'Rocket Science',
            'country' => 'Nowhereland',
        ];
        if ($numberOfOwners < 1) {
            return $business;
        }
        if ($numberOfOwners == 1) {
            // Object.
            $business['ownership'] = $this->samplePerson();
            //unset($business['ownership']['personAddress']['streetName']);
        }
        else {
            // List of objects.
            $business['ownership'] = array_fill(0, $numberOfOwners, $this->samplePerson());
            //unset($business['ownership'][1]['personAddress']['streetName']);
        }
        return $business;
    }

    /**
     * @throws ValidationException
     */
    public function testRuleSetAddress() : ?ValidationRuleSet
    {
        $validator = $this->testInstantiateUncheckedValidator();
        $factory = new RuleSetFactory($validator);

        $source = Helper::parseJsonString(static::getRuleSetJSON('Address'));
        static::assertIsObject($source);

        $ruleSet_address = $factory->make($source);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_address);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_address)->log();
        if (!($ruleSet_address instanceof ValidationRuleSet)) {
            return null;
        }

        $valid = $validator->challenge($this->sampleAddress(), $ruleSet_address, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log(Helper::removeNamespace(__METHOD__) . ':' . __LINE__ . ":\n" . $validator->getLastFailure());
        }
        static::assertTrue($valid, 'Challenge Address');

        return $ruleSet_address;
    }

    /**
     * @return ValidationRuleSet|null
     *
     * @throws ValidationException
     */
    public function testRuleSetPerson() : ?ValidationRuleSet
    {
        $validator = $this->testInstantiateUncheckedValidator();
        $factory = new RuleSetFactory($validator);

        $ruleSet_address = $this->testRuleSetAddress();
        if (!$ruleSet_address) {
            static::assertTrue(true);
            return null;
        }

        $source = Helper::parseJsonString(static::getRuleSetJSON('Person'));
        static::assertIsObject($source);

        $ruleSet_person = $factory->make($source);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_person)->log();
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_person);
        if (!($ruleSet_person instanceof ValidationRuleSet)) {
            return null;
        }

        // Replace child dummy Address ruleset.
        // Replace Person.tableElements...
        $ruleSet_tampered = $ruleSet_person->replaceTableElements(
            // ...with TableElements whose 'address' element has been replaced...
            $ruleSet_person->tableElements->setElementRuleSet(
                'personAddress',
                // ...with real Address ruleset.
                $ruleSet_address
            )
        );
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_tampered);
        // Same as above, but simpler.
        $ruleSet_tampered = $ruleSet_person->replaceTableElementsKeyRuleSet('personAddress', $ruleSet_address);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_tampered)->log();
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_tampered);
        if (!($ruleSet_tampered instanceof ValidationRuleSet)) {
            return null;
        }

        $valid = $validator->challenge($this->samplePerson(), $ruleSet_tampered, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log(Helper::removeNamespace(__METHOD__) . ':' . __LINE__ . ":\n" . $validator->getLastFailure());
        }
        static::assertTrue($valid, 'Challenge Person');

        return $ruleSet_tampered;
    }

    /**
     * @return ValidationRuleSet|null
     *
     * @throws ValidationException
     */
    public function testRuleSetBusiness() : ?ValidationRuleSet
    {
        $validator = $this->testInstantiateUncheckedValidator();
        $factory = new RuleSetFactory($validator);

        $ruleSet_person = $this->testRuleSetPerson();
        if (!$ruleSet_person) {
            static::assertTrue(true);
            return null;
        }

        $source = Helper::parseJsonString(static::getRuleSetJSON('Business_verbatim'));
        static::assertIsObject($source);

        $ruleSet_business = $factory->make($source);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet_business)->log();
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet_business);
        if (!($ruleSet_business instanceof ValidationRuleSet)) {
            return null;
        }

        // No ownership bucket.
        $valid = $validator->challenge($this->sampleBusiness(), $ruleSet_business, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log(Helper::removeNamespace(__METHOD__) . ':' . __LINE__ . ":\n" . $validator->getLastFailure());
        }
        static::assertTrue($valid, 'Challenge Business - no owner');

        // Single ownership - an object.
        $valid = $validator->challenge($this->sampleBusiness(1), $ruleSet_business, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log(Helper::removeNamespace(__METHOD__) . ':' . __LINE__ . ":\n" . $validator->getLastFailure());
        }
        static::assertTrue($valid, 'Challenge Business - single owner');

        // Multiple ownerships - a list of objects.
        $valid = $validator->challenge($this->sampleBusiness(2), $ruleSet_business, static::CHALLENGE_MODE);
        if (!$valid && static::CHALLENGE_MODE) {
            error_log(Helper::removeNamespace(__METHOD__) . ':' . __LINE__ . ":\n" . $validator->getLastFailure());
        }
        static::assertTrue($valid, 'Challenge Business - multiple owners');

        return $ruleSet_business;
    }


}
