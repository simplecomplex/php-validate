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

use SimpleComplex\Validate\Interfaces\RuleSetValidatorInterface;
use SimpleComplex\Validate\RuleSetValidator;

use SimpleComplex\Validate\RuleSet\ValidationRuleSet;
use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;

use SimpleComplex\Tests\Validate\Entity\Bicycle;
use SimpleComplex\Tests\Validate\RuleSetPHP\BicycleRuleSets;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/BicycleTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class BicycleTest extends TestCase
{
    /**
     * @return RuleSetValidator
     */
    public function testInstantiation()
    {
        $validate = RuleSetValidator::getInstance();
        static::assertInstanceOf(RuleSetValidator::class, $validate);

        return $validate;
    }

    /**
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     */
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

        $valid = $validate->challenge($bike, $ruleSet, RuleSetValidatorInterface::RECORD);
        if (!$valid) {
            error_log(__LINE__ . ': pre-converted, no continue:' . "\n" . $validate->getLastFailure());
        }

        $valid = $validate->challenge($bike, $ruleSet, RuleSetValidatorInterface::RECORD | RuleSetValidatorInterface::CONTINUE);
        if (!$valid) {
            error_log(__LINE__ . ': pre-converted:' . "\n" . $validate->getLastFailure());
        }
        static::assertFalse($valid, __LINE__ . ': pre-converted');

        $valid = $validate->challenge($bike, $source);
//        $record = $validate->challengeRecording($bike, $source);
//        if (!$record['passed']) {
//            error_log('runtime converted:' . "\n" . join("\n", $record['record']));
//        }
        static::assertFalse($valid, 'runtime converted');
    }

    /**
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     */
    public function testRuleSetNumericIndexString()
    {
        $validate = $this->testInstantiation();

        $source = BicycleRuleSets::numericIndex();

        $ruleSet = (new RuleSetFactory($validate))->make($source);
        static::assertInstanceOf(ValidationRuleSet::class, $ruleSet);

        $rule_set = $ruleSet->replaceTableElements(
            $ruleSet->tableElements->setElementRuleSet(
                'accessories',
                $ruleSet->tableElements->getElementRuleSet('accessories')
                    ->replaceTableElements(
                        $ruleSet->tableElements->getElementRuleSet('accessories')->tableElements
                            ->setElementRuleSet('whatever', $ruleSet)
                    )
            )
        );
        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($rule_set)->log();

//        $tableElements_accessories = $ruleSet->tableElements->getElementRuleSet('accessories')->tableElements
//            ->setElementRuleSet('whatever', $ruleSet);
//        //$ruleSet->tableElements->rulesByElements['accessories']->tableElements = $tableElements;
//        //$ruleSet->tableElements->rulesByElements['accessories']['whatever'] = true;
//        //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();
////        $tableElements = $tableElements->setElementRuleSet('whatever', $ruleSet);
//        $ruleSet->tableElements->getElementRuleSet('accessories')->replaceTableElements($tableElements);
//
//        $ruleSet = $ruleSet->replaceTableElements($tableElements);
//
//        //$ruleSet =



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

        $valid = $validate->challenge($bike, $ruleSet, RuleSetValidatorInterface::RECORD | RuleSetValidatorInterface::CONTINUE);
        if (!$valid) {
            //\SimpleComplex\Inspect\Inspect::getInstance()->variable($ruleSet)->log();
            error_log(__LINE__ . ': pre-converted:' . "\n" . $validate->getLastFailure());
        }
        static::assertTrue($valid, 'le pre-converted');
    }


}
