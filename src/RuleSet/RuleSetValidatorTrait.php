<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSet;

use SimpleComplex\Validate\Interfaces\RuleSetValidatorInterface;

/**
 * Trait to turn a rule method provider into a ruleset validator.
 *
 * Design technicalities
 * ---------------------
 * BEWARE: This trait is not suitable for a rule provider which may have state
 * (instance vars) that can affect validation.
 * Because this trait's validate() method uses a secondary class's
 * getInstance() method, effectively locking the rule provider and the secondary
 * object together.
 * @see RuleSetValidatorTrait::validate()
 * @see ValidateAgainstRuleSet::getInstance()
 *
 * @mixin \SimpleComplex\Validate\RuleSetValidator
 *
 * @package SimpleComplex\Validate
 */
trait RuleSetValidatorTrait
{
    /**
     * Record of last validate by ruleset failure(s).
     *
     * @var string[]|null
     */
    protected $lastChallengeFailure;

    /**
     * Validate against a ruleset.
     *
     * Arg $options example:
     * RuleSetValidatorInterface::RECORD | RuleSetValidatorInterface::CONTINUE
     *
     * @param mixed $subject
     * @param ValidationRuleSet|object|array $ruleSet
     * @param int $options
     *      Bitmask, see RuleSetValidatorInterface bitmask flag constants.
     *
     * @return bool
     *
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *      Propagated; bad validation ruleset.
     *
     * @see RuleSetValidatorInterface::CONTINUE
     * @see RuleSetValidatorInterface::RECORD
     */
    public function validate($subject, $ruleSet, int $options = 0) : bool
    {
        $this->lastChallengeFailure = null;

        if (!$options) {
            // Reuse existing if any, to save footprint when validating
            // by rulesets consecutivly.
            // Fairly safe because without options (= without recording)
            // the ValidateAgainstRuleSet instance won't have state.
            // But ruins thread safety, because links this rule provider
            // to that ValidateAgainstRuleSet.
            $o = ValidateAgainstRuleSet::getInstance(
                // IDE: $this _is_ RuleProviderInterface.
                $this
            );
        }
        else {
            // Always create new.
            $o = new ValidateAgainstRuleSet(
                // IDE: $this _is_ RuleProviderInterface.
                $this,
                $options
            );
        }
        $passed = $o->challenge($subject, $ruleSet);
        if (!$passed) {
            $this->lastChallengeFailure = $o->getRecord();
        }

        return $passed;
    }

    /**
     * Get failure(s) recorded by last recording validate().
     *
     * @param string $delimiter
     *
     * @return string
     */
    public function getLastFailure(string $delimiter = PHP_EOL) : string
    {
        if ($this->lastChallengeFailure) {
            return join($delimiter, $this->lastChallengeFailure);
        }
        return '';
    }

    /**
     * @deprecated
     *      Use validate() instead.
     *
     * @param $subject
     * @param $ruleSet
     * @param int $options
     * @return bool
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *
     * @see RuleSetValidatorTrait::validate()
     */
    public function challenge($subject, $ruleSet, int $options = 0) : bool
    {
        return $this->validate($subject, $ruleSet, $options);
    }

    /**
     * Validate against a ruleset, recording validation failures.
     *
     * Doesn't stop on failure, continues until the end of the ruleset.
     *
     * @deprecated Use validate() and then getLastFailure()
     *
     * @param mixed $subject
     * @param ValidationRuleSet|array|object $ruleSet
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *      Propagated; bad validation ruleset.
     *
     * @see validate()
     * @see getLastFailure()
     */
    public function challengeRecording($subject, $ruleSet) : array
    {
        $passed = $this->validate(
            $subject,
            $ruleSet,
            RuleSetValidatorInterface::RECORD | RuleSetValidatorInterface::CONTINUE
        );
        return [
            'passed' => $passed,
            'record' => $passed ? [] : ($this->lastChallengeFailure ?? []),
        ];
    }
}
