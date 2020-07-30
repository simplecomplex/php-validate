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
 *
 * Design technicalities
 * ---------------------
 * BEWARE: This trait is not suitable for a rule provider which may have state
 * (instance vars) that can affect validation.
 * Because this trait's challenge() method uses a secondary class's
 * getInstance() method, effectively locking the rule provider and the secondary
 * object together.
 * @see ChallengerTrait::challenge()
 * @see ValidateAgainstRuleSet::getInstance()
 *
 *
 * @mixin AbstractValidator
 *
 * @package SimpleComplex\Validate
 */
trait ChallengerTrait
{
    /**
     * @var string[]|null
     */
    protected $lastChallengeFailure;

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
     * @throws \TypeError  Propagated; arg $ruleSet not object|array.
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *      Propagated; bad validation ruleset.
     */
    public function challenge($subject, $ruleSet, int $options = 0) : bool
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
     * Get failure(s) recorded by last recording challenge.
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
     * Validate against a ruleset, recording validation failures.
     *
     * Doesn't stop on failure, continues until the end of the ruleset.
     *
     * @deprecated Use challenge() and then getLastFailure()
     * @see challenge()
     * @see getLastFailure()
     *
     * @param mixed $subject
     * @param RuleSet\ValidationRuleSet|array|object $ruleSet
     *
     * @return array {
     *      @var bool passed
     *      @var array record
     * }
     *
     * @throws \TypeError  Propagated; arg $ruleSet not object|array.
     * @throws \SimpleComplex\Validate\Exception\ValidationException
     *      Propagated; bad validation ruleset.
     */
    public function challengeRecording($subject, $ruleSet) : array
    {
        $passed = $this->challenge($subject, $ruleSet, ChallengerInterface::RECORD | ChallengerInterface::CONTINUE);
        return [
            'passed' => $passed,
            'record' => $passed ? [] : ($this->lastChallengeFailure ?? []),
        ];
    }
}
