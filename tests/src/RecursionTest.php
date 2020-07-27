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

use SimpleComplex\Validate\AbstractValidator;
use SimpleComplex\Validate\Interfaces\ChallengerInterface;
use SimpleComplex\Validate\UncheckedValidator;
use SimpleComplex\Validate\Validator;

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
    /**
     * @return AbstractValidator
     */
    public function testInstantiation()
    {
        $validate = UncheckedValidator::getInstance();
        static::assertInstanceOf(UncheckedValidator::class, $validate);
        $validate = Validator::getInstance();
        static::assertInstanceOf(Validator::class, $validate);
        static::assertNotInstanceOf(UncheckedValidator::class, $validate);
        $validate = UncheckedValidator::getInstance();
        static::assertInstanceOf(UncheckedValidator::class, $validate);
        static::assertNotInstanceOf(Validator::class, $validate);

//        $validate = new UncheckedValidator();
//        static::assertInstanceOf(UncheckedValidator::class, $validate);
        return $validate;
    }


    public function testRuleSetBicycleOriginal()
    {
        $validate = $this->testInstantiation();

        $source = BicycleRuleSets::original();

        $ruleSet = (new RuleSetFactory($validate))->make($source);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();

        $bike = new Bicycle(
            2,
            true,
            'swooshy',
            [
                'luggageCarrier' => false,
            ],
            null
        );
        $bike->unspecified_1 = 'sneaky';
        $bike->unspecified_2 = 'stealthy';

//        // Fail, because 'class' rule missing namespace.
//        $valid = $validate->challenge($bike, $ruleSet);
//        if (!$valid) {
//            $record = $validate->challengeRecording($bike, $ruleSet);
//            if (!$record['passed']) {
//                error_log(join("\n", $record['record']));
//            }
//        }
//        static::assertFalse($valid);
//
//        if (isset($ruleSet->class)) {
//            // unqualified class name.
//            $ruleSet->class[] = true;
//        }
//        $valid = $validate->challenge($bike, $ruleSet);
//        static::assertTrue($valid);

        $bike->accessories = [
            'lights' => 'not numeric',
            false,
            true,
            'paint',
            13,
            'rubbish',
            //'luggageCarrier' => 'handy',
        ];
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();

        // Wrong, not array listItems string|bool.
        $bike->various = [8];

        $valid = $validate->challenge($bike, $ruleSet, ChallengerInterface::RECORD);
        if (!$valid) {
            error_log('pre-converted, no continue:' . "\n" . join("\n", $validate->getLastFailure()));
        }

        $record = $validate->challengeRecording($bike, $ruleSet);
        if (!$record['passed']) {
            error_log('pre-converted:' . "\n" . join("\n", $record['record']));
        }
        static::assertTrue($valid, 'pre-converted');

        $valid = $validate->challenge($bike, $source);
//        $record = $validate->challengeRecording($bike, $source);
//        if (!$record['passed']) {
//            error_log('runtime converted:' . "\n" . join("\n", $record['record']));
//        }
        static::assertFalse($valid, 'runtime converted');
    }

    public function testRuleSetNumericIndexString()
    {
        $validate = $this->testInstantiation();

        $source = BicycleRuleSets::numericIndex();

        $ruleSet = (new RuleSetFactory($validate))->make($source);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet);

        $tableElements = $ruleSet->tableElements->rulesByElements['accessories']->tableElements;
        $source['tableElements']['rulesByElements']['accessories']['tableElements'] = $tableElements;
        $listItems = $ruleSet->tableElements->rulesByElements['various']->listItems;
        $source['tableElements']['rulesByElements']['various']['listItems'] = $listItems;
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($source)->log();
        $ruleSet = (new RuleSetFactory($validate))->make($source);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet);
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();

        $bike = new Bicycle(
            null,
            true,
            'swooshy',
            [
                'luggageCarrier' => false,
            ],
            null
        );

//        unset($bike->saddle);
//        $bike->sound = false;

        $bike->accessories = [
            'whatever',
        ];
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();

        $valid = $validate->challenge($bike, $ruleSet);
        $record = $validate->challengeRecording($bike, $ruleSet);
        if (!$record['passed']) {
            error_log('pre-converted:' . "\n" . join("\n", $record['record']));
        }
        static::assertTrue($valid, 'pre-converted');
    }


}
