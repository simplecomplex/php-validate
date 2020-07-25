<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\ChallengerInterface;

use SimpleComplex\Validate\RuleSet\ValidationRuleSet;

/**
 * Validator checking against a ruleset.
 *
 * Supports recursive validation of object|array containers.
 *
 * The class must implement RuleProviderInterface,
 * so that $this is a RuleProviderInterface.
 * @see \SimpleComplex\Validate\Interfaces\RuleProviderInterface
 *
 * @package SimpleComplex\Validate
 */
trait ChallengerTrait
{
    /**
     * @var ValidateAgainstRuleSet|null
     */
    protected $lastValidateAgainstRuleSet;

    /**
     * Validate against a ruleset.
     *
     * Arg $options example:
     * ChallengerInterface::RECORD | ChallengerInterface::CONTINUE
     * @see ChallengerInterface::RECORD
     * @see ChallengerInterface::CONTINUE
     *
     * @param mixed $subject
     * @param ValidationRuleSet|object|array $ruleSet
     * @param int $options
     *      Bitmask, see ChallengerInterface bitmask flag constants.
     *
     * @return bool
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challenge($subject, $ruleSet, int $options = 0) : bool
    {
        $this->lastValidateAgainstRuleSet = $o = new ValidateAgainstRuleSet(
            // IDE: $this _is_ RuleProviderInterface.
            $this,
            $options
        );

        return $o->challenge($subject, $ruleSet);
    }

    /**
     * Get failure(s) recorded by last recording challenge.
     *
     * Clears the record; unlinks last ValidateAgainstRuleSet, if any.
     *
     * @return string[]
     */
    public function getLastFailure() : array
    {
        if ($this->lastValidateAgainstRuleSet) {
            $a = $this->lastValidateAgainstRuleSet->getRecord();
            $this->lastValidateAgainstRuleSet = null;
            return $a;
        }
        return [];
    }

    /**
     * Validate against a ruleset, recording validation failures.
     *
     * Doesn't stop on failure, continues until the end of the ruleset.
     *
     * @deprecated Use challenge() and then getLastFailure()
     * @see challenge()
     * @see getLastFailure()
     *
     * @code
     * $good_bike = Validate::make()->challengeRecording($bike, $rules);
     * if (empty($good_bike['passed'])) {
     *   echo "Failed:\n" . join("\n", $good_bike['record']) . "\n";
     * }
     * @endcode
     *
     * @param mixed $subject
     * @param RuleSet\ValidationRuleSet|array|object $ruleSet
     * @param string $keyPath
     *      Name of element to validate, or key path to it.
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function challengeRecording($subject, $ruleSet, string $keyPath = '@') : array
    {
        $this->lastValidateAgainstRuleSet = $o = new ValidateAgainstRuleSet(
            // IDE: $this _is_ RuleProviderInterface.
            $this,
            ChallengerInterface::RECORD | ChallengerInterface::CONTINUE
        );

        $passed = $o->challenge($subject, $ruleSet, $keyPath);
        return [
            'passed' => $passed,
            'record' => $passed ? [] : $o->getRecord(),
        ];
    }
}
