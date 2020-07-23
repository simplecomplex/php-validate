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
use SimpleComplex\Validate\Helper\Helper;

use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Pseudo rule listing ValidationRuleSets of elements of a 'loopable'
 * object|array subject.
 * @see TypeRulesTrait::loopable()
 *
 * TableElements is an optional property of ValidationRuleSet.
 * @see ValidationRuleSet::$tableElements
 *
 * Modifiers exclusive, whitelist and blacklist are mutually exclusive.
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
     * @var mixed[]
     */
    const MODIFIERS = [
        'exclusive' => null,
        'whitelist' => null,
        'blacklist' => null,
    ];

    /**
     * Keys of the elements specified.
     *
     * Helps securing that all keys are string.
     * PHP numeric index is not consistently integer.
     * Without keys we would have to do array_keys() repetetively.
     *
     * @var string[]
     */
    public $keys = [];

    /**
     * Subject object|array must only contain keys defined by rulesByElements.
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
     * @var ValidationRuleSet[]
     */
    public $rulesByElements = [];


    /**
     * If no rulesByElements nor modifiers then arg $tableElements itself
     * is used as rules-by-elements.
     *
     * Assumes that arg $tableElements in itself is the rulesByElements
     * hashtable, if $tableElements doesn't have a rulesByElements property
     * nor any of the modifier properties.
     *
     * @param RuleSetFactory $ruleSetFactory
     * @param object $tableElements
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(
        RuleSetFactory $ruleSetFactory, object $tableElements, int $depth = 0, string $keyPath = 'root'
    ) {
        $this->defineModifiers($tableElements, $depth, $keyPath);
        $this->defineRulesByElements($ruleSetFactory, $tableElements, $depth, $keyPath);
    }

    /**
     * @see TableElements::__construct()
     *
     * @param object $tableElements
     * @param int $depth
     * @param string $keyPath
     */
    protected function defineModifiers($tableElements, $depth, $keyPath) : void
    {
        $modifiers = [];
        if (!empty($tableElements->exclusive)) {
            $this->exclusive = true;
            $modifiers[] = 'exclusive';
        }
        if (!empty($tableElements->whitelist)) {
            if (!is_array($tableElements->whitelist)) {
                throw new InvalidRuleException(
                    'Validation tableElements.whitelist type[' . Helper::getType($tableElements->whitelist)
                    . '] is not array' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            // PHP numeric index is not consistently integer.
            foreach ($tableElements->whitelist as $key) {
                $this->whitelist[] = '' . $key;
            }
            $modifiers[] = 'whitelist';
        }
        if (!empty($tableElements->blacklist)) {
            if (!is_array($tableElements->blacklist)) {
                throw new InvalidRuleException(
                    'Validation tableElements.blacklist type[' . Helper::getType($tableElements->blacklist)
                    . '] is not array' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            // PHP numeric index is not consistently integer.
            foreach ($tableElements->blacklist as $key) {
                $this->blacklist[] = '' . $key;
            }
            $modifiers[] = 'blacklist';
        }
        if (count($modifiers) > 1) {
            throw new InvalidRuleException(
                'Validation tableElements only accepts a single exclusive|whitelist|blacklist modifier, saw '
                . count($modifiers) . ' modifiers[' . join(', ', $modifiers)
                . ']' . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }
    }

    /**
     * Assumes that arg $tableElements in itself is the rulesByElements
     * hashtable, if $tableElements doesn't have a rulesByElements property
     * nor any of the modifier properties.
     *
     * @see TableElements::__construct()
     *
     * @param RuleSetFactory $ruleSetFactory
     * @param object $tableElements
     * @param int $depth
     * @param string $keyPath
     */
    protected function defineRulesByElements($ruleSetFactory, $tableElements, $depth, $keyPath) : void
    {
        if (property_exists($tableElements, 'rulesByElements')) {
            if (!is_object($tableElements->rulesByElements) && !is_array($tableElements->rulesByElements)) {
                throw new InvalidRuleException(
                    'Validation tableElements.rulesByElements type[' . Helper::getType($tableElements->rulesByElements)
                    . '] is not object|array' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $self_rulesByElements = false;
            $rulesByElements = $tableElements->rulesByElements;
        }
        else {
            // Assume that arg $tableElements in itself is the rulesByElements
            // hashtable.
            $self_rulesByElements = true;
            // Ensure that no modifier exists.
            $mods = array_keys(static::MODIFIERS);
            $mods_found = [];
            foreach ($mods as $mod) {
                if (property_exists($tableElements, $mod)) {
                    $self_rulesByElements = false;
                    $mods_found[] = $mod;
                }
            }
            if (!$self_rulesByElements) {
                throw new InvalidRuleException(
                    'Validation tableElements misses child rulesByElements, and has modifiers['
                    . join(', ', $mods_found) . '], thus can\'t assume tableElements in itself is rulesByElements'
                    . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $rulesByElements = $tableElements;
        }

        $class_rule_set = static::CLASS_RULE_SET;
        foreach ($rulesByElements as $key => $ruleSet) {
            // PHP numeric index is not consistently integer.
            $sKey = '' . $key;
            if ($ruleSet instanceof $class_rule_set) {
                $this->rulesByElements[$sKey] = $ruleSet;
            }
            elseif (is_object($ruleSet)) {
                $this->rulesByElements[$sKey] =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    $ruleSetFactory->make($ruleSet, $depth + 1, $keyPath . ' > ' . $key);
            }
            elseif (is_array($ruleSet)) {
                $this->rulesByElements[$sKey] =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    $ruleSetFactory->make((object) $ruleSet, $depth + 1, $keyPath . ' > ' . $key);
            }
            else {
                throw new InvalidRuleException(
                    'Validation tableElements'
                    . (!$self_rulesByElements ? '.rulesByElements' : ' using self as rulesByElements')
                    . ' key[' . $key . '] type[' . Helper::getType($ruleSet) . '] is not object|array'
                    . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }

            if ($this->whitelist) {
                if (in_array($sKey, $this->whitelist, true)) {
                    throw new InvalidRuleException(
                        'Validation tableElements.whitelist cannot contain key[' . $key. '] also specified'
                        . ' in rulesByElements' . ', at (' . $depth . ') ' . $keyPath . '.'
                    );
                }
            }
            elseif ($this->blacklist && in_array($sKey, $this->blacklist, true)) {
                throw new InvalidRuleException(
                    'Validation tableElements.blacklist cannot contain key[' . $key. '] also specified'
                    . ' in rulesByElements' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }

            $this->keys[] = $sKey;
        }
    }
}
