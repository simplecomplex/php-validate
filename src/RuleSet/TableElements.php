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
 * Immutable. All properties are read-only to prevent tampering.
 * Meant to be created by a generator, itself issued by a factory.
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetFactory
 * @property-read string[] $keys
 * @property-read bool $exclusive
 * @property-read string[] $whitelist
 * @property-read string[] $blacklist
 * @property-read ValidationRuleSet[] $rulesByElements
 *
 * @package SimpleComplex\Validate
 */
class TableElements
{
    /**
     * @var string
     */
    protected const CLASS_RULE_SET = ValidationRuleSet::class;

    /**
     * @var mixed[]
     */
    protected const MODIFIERS = [
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
    protected $keys = [];

    /**
     * Subject object|array must only contain keys defined by rulesByElements.
     *
     * @var bool
     */
    protected $exclusive = false;

    /**
     * Subject object|array must _only_ contain these keys,
     * apart from the keys defined by rulesByElements.
     *
     * @var string[]
     */
    protected $whitelist = [];

    /**
     * Subject array|object must _not_ contain these keys,
     * apart from the keys defined by rulesByElements.
     *
     * @var string[]
     */
    protected $blacklist = [];

    /**
     * @var ValidationRuleSet[]
     */
    protected $rulesByElements = [];


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
     * @return mixed[] {
     *      @var bool $exclusive
     *      @var string[] $whitelist
     *      @var string[] $blacklist
     * }
     */
    public function getModifiers()
    {
        return [
            'exclusive' => $this->exclusive,
            'whitelist' => $this->whitelist,
            'blacklist' => $this->blacklist,
        ];
    }

    /**
     * @return string[]
     */
    public function getKeys() : array
    {
        return $this->keys;
    }

    /**
     * @param string $key
     *
     * @return ValidationRuleSet|null
     */
    public function getElementRuleSet(string $key) : ?ValidationRuleSet
    {
        return $this->rulesByElements[$key] ?? null;
    }

    /**
     * Appends if nonexistent - the order of elements has no consequence.
     *
     * Overwrites if existent, and then removes the key from modifiers
     * whitelist, blacklist.
     *
     * @param string $key
     * @param ValidationRuleSet $ruleSet
     *
     * @return self
     *      New TableElements; is immutable.
     */
    public function setElementRuleSet(string $key, ValidationRuleSet $ruleSet) : self
    {
        $that = clone $this;
        $exists = in_array($key, $that->keys, true);
        if (!$exists) {
            if ($that->whitelist && ($index = array_search($key, $that->whitelist, true))) {
                array_splice($that->whitelist, $index, 1);
            }
            elseif ($that->blacklist && ($index = array_search($key, $that->blacklist, true))) {
                array_splice($that->blacklist, $index, 1);
            }
            $that->keys[] = $key;
        }
        $that->rulesByElements[$key] = $ruleSet;
        return $that;
    }

    /**
     * @param string $key
     * @param bool $ifExists
     *      True: don't err it that key doesn't exist.
     *
     * @return self
     *      $this if the key doesn't exist and true arg $ifExists.
     *      Otherwise new TableElements; is immutable.
     *
     * @throws InvalidRuleException
     *      If the key doesn't exist and false arg $ifExists.
     *      If current TableElements only contain one element; TableElements
     *      is not allowed to be empty.
     */
    public function removeElementRuleSet(string $key, bool $ifExists = false) : self
    {
        $index = array_search($key, $this->keys, true);
        if ($index === false) {
            if (!$ifExists) {
                throw new InvalidRuleException('Cannot remove nonexistent element key[' . $key . '].');
            }
            return $this;
        }

        $size = count($this->keys);
        if ($size < 2) {
            throw new InvalidRuleException(
                'Removal of element key[' . $key . '] denied because tableElements would become empty.'
            );
        }
        $that = clone $this;
        unset($that->rulesByElements[$key]);
        array_splice($that->keys, $index, 1);

        return $that;
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
     *
     * @return void
     *
     * @throws InvalidRuleException
     */
    protected function defineRulesByElements(
        RuleSetFactory $ruleSetFactory, object $tableElements, int $depth, string $keyPath
    ) : void {
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

        if (!count($this->keys)) {
            throw new InvalidRuleException(
                'Validation tableElements.rulesByElements is not allowed to empty'
                . ', at (' . $depth . ') ' . $keyPath . '.'
            );
        }
    }

    /**
     * Sparse info only, skips keys and skips empty modifiers.
     * And if no modifiers, lists rulesByElements directly
     * instead of in a rulesByElements bucket.
     *
     * @return array
     */
    public function __debugInfo() : array
    {
        // return get_object_vars($this);
        $a = [];
        // Ignore $keys.
        foreach (array_keys(static::MODIFIERS) as $modifier) {
            if ($this->{$modifier}) {
                $a[$modifier] = $this->{$modifier};
            }
        }
        if (!$a) {
            return $this->rulesByElements;
        }
        $a['rulesByElements'] = $this->rulesByElements;
        return $a;
    }
}
