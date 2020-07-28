<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSet;

use SimpleComplex\Validate\RuleSetFactory\RuleSetFactory;
use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Exception\InvalidArgumentException;

/**
 * Pseudo rule using a common ruleset of every element of object|array subject.
 *
 * listItems combined with tableElements is allowed.
 * Relevant for a container derived from XML, which allows hash table
 * elements and list items within the same container (XML sucks ;-).
 * @see TableElements
 *
 * Immutable. All properties are read-only to prevent tampering.
 * Meant to be created by a generator, itself issued by a factory.
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetFactory
 * @property-read int $minOccur
 * @property-read int $maxOccur
 * @property-read ValidationRuleSet $itemRules
 *
 * @package SimpleComplex\Validate
 */
class ListItems
{
    /**
     * @var string
     */
    protected const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * @var mixed[]
     */
    protected const MODIFIERS = [
        'minOccur' => null,
        'maxOccur' => null,
    ];

    /**
     * Zero means no limitation.
     *
     * @var int
     */
    protected $minOccur = 0;

    /**
     * Zero means no limitation.
     *
     * @var int
     */
    protected $maxOccur = 0;

    /**
     * @var ValidationRuleSet
     */
    protected $itemRules;

    /**
     * If no itemRules nor modifiers then arg $tableElements itself
     * is used as item-rules.
     *
     * @param RuleSetFactory $ruleSetFactory
     * @param object $listItems
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(
        RuleSetFactory $ruleSetFactory, object $listItems, int $depth = 0, string $keyPath = 'root'
    ) {
        $this->defineModifiers($listItems, $depth, $keyPath);
        $this->defineItemRules($ruleSetFactory, $listItems, $depth, $keyPath);
    }

    /**
     * @param string $key
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function __get(string $key)
    {
        if (isset($this->{$key})) {
            return $this->{$key};
        }
        throw new InvalidArgumentException(get_class($this) . ' instance exposes no property[' . $key . '].');
    }

    /**
     * @param ValidationRuleSet $ruleSet
     *
     * @return self
     *      Immutable.
     */
    public function replaceItemRuleSet(ValidationRuleSet $ruleSet) : self
    {
        $that = clone $this;
        $that->itemRules = $ruleSet;
        return $that;
    }

    /**
     * @param object $listItems
     * @param int $depth
     * @param string $keyPath
     */
    protected function defineModifiers(object $listItems, int $depth, string $keyPath) : void
    {
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
    }

    /**
     * @param RuleSetFactory $ruleSetFactory
     * @param object $listItems
     * @param int $depth
     * @param string $keyPath
     */
    protected function defineItemRules(
        RuleSetFactory $ruleSetFactory, object $listItems, int $depth, string $keyPath
    ) : void {
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
            foreach (static::MODIFIERS as $mod) {
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

    /**
     * @return array
     */
    public function __debugInfo() : array
    {
        return get_object_vars($this);
    }
}
