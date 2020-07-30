<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\RuleSet;

use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\BadMethodCallException;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Validation rule set.
 *
 * @see AbstractRuleProvider::challenge()
 *
 * Immutable. All properties are read-only to prevent tampering.
 * Meant to be created by a generator, itself issued by a factory.
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator
 * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetFactory
 *
 *
 * @property-read bool|array *
 *      Provider rules are set/declared as instance vars dynamically.
 *
 * @property-read boolean|undefined $optional
 *      Flags that the object|array subject element do not have to exist.
 *
 * @property-read boolean|undefined $nullable
 *      Flags that the element is allowed to be null.
 *      Null is not the same as non-existent (optional).
 *
 * @property-read boolean|undefined $empty
 * @see TypeRulesTrait::empty()
 *
 * @property-read boolean|undefined $nonEmpty
 * @see TypeRulesTrait::nonEmpty()
 *
 * @property-read array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *      Bucket values must be scalar|null.
 *
 * @property-read ValidationRuleSet|undefined $alternativeRuleSet
 *      Alternative rule set used if subject doesn't comply with
 *      other - typically type checking - rules and/or alternativeEnum.
 *
 * @property-read TableElements|undefined $tableElements {
 *      @var ValidationRuleSet[] $rulesByElements
 *          ValidationRuleSet by element key.
 *      @var string[] $keys
 *          Keys of rulesByElements.
 *      @var bool|undefined $exclusive
 *          Subject object|array must only contain keys defined
 *          by rulesByElements.
 *      @var array|undefined $whitelist
 *          Subject object|array must only contain these keys,
 *          apart from the keys defined by $rulesByElements.
 *      @var array|undefined $blacklist
 *          Subject object|array must not contain these keys,
 *          apart from the keys defined by $rulesByElements.
 * }
 *      Rule listing ValidationRuleSets of elements of object|array subject.
 *
 *      Flags/lists exclusive, whitelist and blacklist are mutually exclusive.
 *      If subject is \ArrayAccess without a getArrayCopy() method then that
 *      will count as validation failure, because validation not possible.
 *
 *      tableElements combined with listItems is allowed.
 *      If tableElements pass then listItems will be ignored.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 * @property-read ListItems|undefined $listItems {
 *      @var ValidationRuleSet|object|array $itemRules
 *          Rule set which will be applied on every item.
 *      @var int|undefined $minOccur
 *      @var int|undefined $maxOccur
 * }
 *      Rule representing every element of object|array subject.
 *
 *      listItems combined with tableElements is allowed.
 *      If tableElements pass then listItems will be ignored.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *
 * Design considerations - why no \Traversable or \Iterator?
 * ---------------------------------------------------------
 * The only benefits would be i. that possibly undefined properties could be
 * assessed directly (without isset()) and ii. that the (at)property
 * documentation would be formally correct (not ...|undefined).
 * The downside would be deteriorated performance.
 * The class is primarily aimed at ValidateAgainstRuleSet use. Convenience
 * of access for other purposes is not a priority.
 *
 * @package SimpleComplex\Validate
 * @noinspection PhpUndefinedClassInspection
 */
class ValidationRuleSet
{
    /**
     * @var array
     */
    protected $rules;

    /**
     * @param array $rules
     *
     * @throws InvalidArgumentException
     *      Arg $rules empty.
     */
    public function __construct(array $rules)
    {
        if (!$rules) {
            throw new InvalidArgumentException('Arg $rules cannot be empty.');
        }
        $this->defineRules($rules);
    }

    /**
     * @param array $rules
     *
     * @throws BadMethodCallException
     *      If called a second time; upon intantiation.
     */
    protected function defineRules(array $rules) : void
    {
        if ($this->rules) {
            throw new BadMethodCallException(
                get_class($this) . ' is frozen, cannot populate rules upon instantiation.'
            );
        }
        $this->rules = $rules;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function __isset(string $key)
    {
        return isset($this->rules[$key]);
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function __get(string $key)
    {
        // No exception; asking for nonexistent is necessary, since properties
        // don't exist at all if not defined.
        return $this->rules[$key] ?? null;
    }

    /**
     * Instance frozen upon instantiation, setting any property is illegal.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     *
     * @throws BadMethodCallException
     *      Always, at any call.
     */
    public function __set(string $key, $value)
    {
        throw new BadMethodCallException(get_class($this) . ' is frozen, cannot set property[' . $key . '].');
    }

    /**
     * @return array
     */
    public function exportRules() : array
    {
        return $this->rules;
    }

    /**
     * Replace existing tableElements pseudo-rule with new.
     *
     * Adding or removing isn't legal, because that could affect the validation
     * ruleset as a whole in an unforeseeable manner.
     * Use the generator instead.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator
     *
     * @param TableElements $tableElements
     *
     * @return self
     *      New ValidationRuleSet; is immutable.
     *
     * @throws InvalidRuleException
     *      The ruleset doesn't already contain tableElements.
     */
    public function replaceTableElements(TableElements $tableElements) : self
    {
        if (isset($this->rules['tableElements'])) {
            $that = clone $this;
            $that->rules['tableElements'] = $tableElements;
            return $that;
        }
        throw new InvalidRuleException(
            'Cannot replace tableElements of ruleset that doesn\'t already have tableElements.'
        );
    }

    /**
     * Replace ruleset of key in child tableElements.
     *
     * Convenience method, instead of counter-intuitive immutable calls:
     * (new parent ruleset) = (parent ruleset)->replaceTableElements(
     *     (parent ruleset)->tableElements->setElementRuleSet(
     *         'key',
     *         (replacer child ruleset)
     *     )
     * )
     *
     * @param string $key
     * @param ValidationRuleSet $ruleSet
     *
     * @return self
     *      New ValidationRuleSet; is immutable.
     */
    public function replaceTableElementsKeyRuleSet(string $key, ValidationRuleSet $ruleSet) : self
    {
        if (isset($this->rules['tableElements'])) {
            $that = clone $this;
            /** @var TableElements $tableElements */
            $tableElements = $that->rules['tableElements'];
            if (!$tableElements->getElementRuleSet($key)) {
                throw new InvalidArgumentException(
                    'Cannot replace ruleset in tableElements because the tableElements has no key[' . $key . '].'
                );
            }
            $that->rules['tableElements'] = $tableElements->setElementRuleSet($key, $ruleSet);
            return $that;
        }
        throw new InvalidRuleException(
            'Cannot replace ruleset in tableElements of ruleset that doesn\'t have tableElements.'
        );
    }

    /**
     * Replace existing listItems pseudo-rule with new.
     *
     * Adding or removing isn't legal, because that could affect the validation
     * ruleset as a whole in an unforeseeable manner.
     * Use the generator instead.
     * @see \SimpleComplex\Validate\RuleSetFactory\RuleSetGenerator
     *
     * @param ListItems $listItems
     *
     * @return self
     *      New ValidationRuleSet; is immutable.
     *
     * @throws InvalidRuleException
     *      The ruleset doesn't already contain listItems.
     */
    public function replaceListItems(ListItems $listItems) : self
    {
        if (isset($this->rules['listItems'])) {
            $that = clone $this;
            $that->rules['listItems'] = $listItems;
            return $that;
        }
        throw new InvalidRuleException(
            'Cannot replace listItems of ruleset that doesn\'t already have listItems.'
        );
    }

    /**
     * @return array
     */
    public function __debugInfo() : array
    {
        return $this->rules;
    }
}
