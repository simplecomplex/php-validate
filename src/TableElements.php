<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Pseudo rule listing ValidationRuleSets of elements of a 'loopable'
 * object|array subject.
 * @see Validate::loopable()
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
     * @var ValidationRuleSet[]
     */
    public $rulesByElements = [];

    /**
     * Keys of the elements specified.
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
     * Assumes that arg $tableElements in itself is the rulesByElements
     * hashtable, if $tableElements doesn't have a rulesByElements property
     * nor any of the modifier properties.
     *
     * @param object $tableElements
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     */
    public function __construct(
        object $tableElements, RuleProviderInterface $ruleProvider, int $depth = 0, string $keyPath = 'root'
    ) {
        // Body moved to separate methods for simpler override.
        $this->defineModifiers($tableElements, $depth, $keyPath);
        $this->defineRulesByElements($tableElements, $ruleProvider, $depth, $keyPath);
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
    }

    /**
     * Assumes that arg $tableElements in itself is the rulesByElements
     * hashtable, if $tableElements doesn't have a rulesByElements property
     * nor any of the modifier properties.
     *
     * @see TableElements::__construct()
     *
     * @param object $tableElements
     * @param RuleProviderInterface $ruleProvider
     * @param int $depth
     * @param string $keyPath
     */
    protected function defineRulesByElements($tableElements, $ruleProvider, $depth, $keyPath) : void
    {
        if (property_exists($tableElements, 'rulesByElements')) {
            if (!is_object($tableElements->rulesByElements) && !is_array($tableElements->rulesByElements)) {
                throw new InvalidRuleException(
                    'Validation tableElements.rulesByElements type[' . Helper::getType($tableElements->rulesByElements)
                    . '] is not object|array, at (' . $depth . ') ' . $keyPath . '.'
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
            foreach ($mods as $mod) {
                if (property_exists($tableElements, $mod)) {
                    $self_rulesByElements = false;
                    break;
                }
            }
            if (!$self_rulesByElements) {
                throw new InvalidRuleException(
                    'Validation tableElements misses child rulesByElements, and has one or more modifiers'
                    . ', thus cannot assume tableElements in itself is the rulesByElements'
                    . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }
            $rulesByElements = $tableElements;
        }

        $class_rule_set = static::CLASS_RULE_SET;
        foreach ($rulesByElements as $key => $ruleSet) {
            if ($ruleSet instanceof ValidationRuleSet) {
                $this->rulesByElements[$key] = $ruleSet;
            }
            elseif (is_object($ruleSet)) {
                $this->rulesByElements[$key] =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    new $class_rule_set($ruleSet, $ruleProvider, $depth + 1, $keyPath . ' > ' . $key);
            }
            elseif (is_array($ruleSet)) {
                $this->rulesByElements[$key] =
                    /**
                     * new ValidationRuleSet(
                     * @see ValidationRuleSet::__construct()
                     */
                    new $class_rule_set((object) $ruleSet, $ruleProvider, $depth + 1, $keyPath . ' > ' . $key);
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
                if (in_array($key, $this->whitelist, true)) {
                    throw new InvalidRuleException(
                        'Validation tableElements.whitelist cannot contain key[' . $key. '] also specified'
                        . ' in rulesByElements' . ', at (' . $depth . ') ' . $keyPath . '.'
                    );
                }
            }
            elseif ($this->blacklist && in_array($key, $this->blacklist, true)) {
                throw new InvalidRuleException(
                    'Validation tableElements.blacklist cannot contain key[' . $key. '] also specified'
                    . ' in rulesByElements' . ', at (' . $depth . ') ' . $keyPath . '.'
                );
            }

            $this->keys[] = $key;
        }
    }
}
