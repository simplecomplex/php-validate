<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Exception\InvalidRuleException;
use SimpleComplex\Validate\Interfaces\RuleProviderInterface;

/**
 * Pseudo rule listing ValidationRuleSets of elements of object|array subject.
 * @see ValidationRuleSet::$tableElements
 *
 * Flags/lists exclusive, whitelist and blacklist are mutually exclusive.
 *
 * tableElements combined with listItems is allowed.
 * Relevant for a container derived from XML, which allows hash table
 * elements and list items within the same container (XML sucks ;-).
 * @see ListItems
 *
 * @package SimpleComplex\Validate
 */
class TableElements
{
    /**
     * @var string
     */
    const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * @var ValidationRuleSet
     */
    public $rulesByElements = [];

    /**
     * Subject object|array must not contain any other keys
     * than those defined by rulesByElements.
     *
     * @var bool
     */
    public $exclusive = false;

    /**
     * Subject object|array must _only_ contain these keys,
     * apart from the keys defined by rulesByElements.
     *
     * @var string[]
     */
    public $whitelist = [];

    /**
     * Subject array|object must _not_ contain these keys,
     * apart from the keys defined by rulesByElements.
     *
     * @var string[]
     */
    public $blacklist = [];


    /**
     * @param object $tableElements
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(
        object $tableElements, RuleProviderInterface $ruleProvider, int $depth = 0, string $keyPath = 'root'
    ) {
        $modifiers = [];
        if (!empty($tableElements->exclusive)) {
            $this->exclusive = true;
            $modifiers[] = 'exclusive';
        }
        if (!empty($tableElements->whitelist)) {
            if (!is_array($tableElements->whitelist)) {
                throw new InvalidRuleException(
                    'Validation tableElements.whitelist type[' . Helper::getType($tableElements->whitelist)
                    . '] is not array, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $this->whitelist = $tableElements->whitelist;
            $modifiers[] = 'whitelist';
        }
        if (!empty($tableElements->blacklist)) {
            if (!is_array($tableElements->blacklist)) {
                throw new InvalidRuleException(
                    'Validation tableElements.blacklist type[' . Helper::getType($tableElements->blacklist)
                    . '] is not array, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $this->blacklist = $tableElements->blacklist;
            $modifiers[] = 'blacklist';
        }
        if (count($modifiers) > 1) {
            throw new InvalidRuleException(
                'Validation tableElements only accepts a single exclusive|whitelist|blacklist modifier, saw '
                . count($modifiers) . ' modifiers[' . join(', ', $modifiers)
                . '], at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        if (property_exists($tableElements, 'rulesByElements')) {
            if (!is_object($tableElements->rulesByElements) && !is_array($tableElements->rulesByElements)) {
                throw new InvalidRuleException(
                    'Validation tableElements.rulesByElements type[' . Helper::getType($tableElements->rulesByElements)
                    . '] is not object|array, at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $rulesByElements = $tableElements->rulesByElements;
        }
        elseif (
            !property_exists($tableElements, 'exclusive')
            && !property_exists($tableElements, 'whitelist')
            && !property_exists($tableElements, 'blacklist')
        ) {
            // Assume that arg $tableElements in itself is the rulesByElements
            // hashtable.
            $rulesByElements = $tableElements;
        }
        else {
            throw new InvalidRuleException(
                'Validation tableElements misses child rulesByElements, and has one or more modifiers'
                . ', thus cannot assume tableElements in itself is the rulesByElements'
                . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }

        $class_rule_set = static::CLASS_RULE_SET;
        foreach ($rulesByElements as $key => $ruleSet) {
            $this->rulesByElements[$key] = $ruleSet instanceof $class_rule_set ? $ruleSet :
                /** @see ValidationRuleSet::__construct() */
                new $class_rule_set($ruleSet, $ruleProvider, $depth + 1, $keyPath . ' > ' . $key);
        }
    }
}
