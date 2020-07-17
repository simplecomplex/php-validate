<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;

use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Pseudo rule representing every element of object|array subject.
 *
 * listItems combined with tableElements is allowed.
 * Relevant for a container derived from XML, which allows hash table
 * elements and list items within the same container (XML sucks ;-).
 * @see TableElements
 *
 * @package SimpleComplex\Validate
 */
class ListItems
{
    /**
     * @var string
     */
    const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * Zero means no limitation.
     *
     * @var int
     */
    public $minOccur = 0;

    /**
     * Zero means no limitation.
     *
     * @var int
     */
    public $maxOccur = 0;

    /**
     * @var ValidationRuleSet
     */
    public $itemRules;

    /**
     * Assumes that arg $listItems in itself is the itemRules ruleset,
     * if $listItems doesn't have a itemRules property
     * nor any of the modifier properties.
     *
     * @param RuleSetFactory $ruleSetFactory
     * @param object $listItems
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(
        RuleSetFactory $ruleSetFactory, object $listItems, int $depth = 0, string $keyPath = 'root'
    ) {
        if (isset($listItems->minOccur)) {
            if (!is_int($listItems->minOccur)) {
                throw new InvalidRuleException(
                    'Validation listItems.minOccur type[' . Helper::getType($listItems->minOccur)
                    . '] is not int, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            if ($listItems->minOccur < 0) {
                throw new InvalidRuleException(
                    'Validation listItems.minOccur[' . $listItems->minOccur
                    . '] cannot be less than zero, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $this->minOccur = $listItems->minOccur;
        }
        if (isset($listItems->maxOccur)) {
            if (!is_int($listItems->maxOccur)) {
                throw new InvalidRuleException(
                    'Validation listItems.maxOccur type[' . Helper::getType($listItems->maxOccur)
                    . '] is not int, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            if ($listItems->maxOccur) {
                if ($listItems->maxOccur < $this->minOccur) {
                    throw new InvalidRuleException(
                        'Validation listItems.maxOccur[' . $listItems->maxOccur . '] cannot be less than minOccur['
                        . $this->minOccur . '], at (' . $depth . ') ' . $keyPath . '.'
                    );
                }
                $this->maxOccur = $listItems->maxOccur;
            }
        }

        $class_rule_set = static::CLASS_RULE_SET;
        if (property_exists($listItems, 'itemRules')) {
            if (is_object($listItems->itemRules)) {
                if ($listItems->itemRules instanceof $class_rule_set) {
                    $this->itemRules = $listItems->itemRules;
                }
                else {
                    $this->itemRules =
                        /**
                         * new ValidationRuleSet(
                         * @see ValidationRuleSet::__construct()
                         */
                        $ruleSetFactory->make($listItems->itemRules, $depth + 1, $keyPath . '(itemRules)');
                }
            }
            elseif (is_array($listItems->itemRules)) {
                $this->itemRules =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    $ruleSetFactory->make((object) $listItems->itemRules, $depth + 1, $keyPath . '(itemRules)');
            }
            else {
                throw new InvalidRuleException(
                    'Validation listItems.itemRules type[' . Helper::getType($listItems->itemRules)
                    . '] is not object|array, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
        }
        else {
            $mods_found = [];
            foreach (['minOccur', 'maxOccur'] as $mod) {
                if (property_exists($listItems, $mod)) {
                    $mods_found[] = $mod;
                }
            }
            if (!$mods_found) {
                // Assume that arg $listItems in itself is the itemRules ruleset.
                $this->itemRules =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    $ruleSetFactory->make($listItems, $depth + 1, $keyPath . '(itemRules)');
            }
            else {
                throw new InvalidRuleException(
                    'Validation listItems misses child itemRules, and has modifiers[' . join(', ', $mods_found)
                    . '], thus can\'t assume listItems in itself is itemRules'
                    . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
        }
    }
}
