<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

/**
 * Validation rule set.
 *
 * @see AbstractRuleProvider::challenge()
 *
 * Meant to be created by a generator, issued by a factory.
 * @see RuleSetFactory\RuleSetGenerator
 * @see RuleSetFactory\RuleSetFactory
 *
 *
 * @property bool|array *
 *      Provider rules are set/declared as instance vars dynamically.
 *
 * @property boolean|undefined $optional
 *      Flags that the object|array subject element do not have to exist.
 *
 * @property boolean|undefined $nullable
 *      Flags that the element is allowed to be null.
 *      Null is not the same as non-existent (optional).
 *
 * @property boolean|undefined $empty
 * @see TypeRulesTrait::empty()
 *
 * @property boolean|undefined $nonEmpty
 * @see TypeRulesTrait::nonEmpty()
 *
 * @property array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *      Bucket values must be scalar|null.
 *
 * @property ValidationRuleSet|undefined $alternativeRuleSet
 *      Alternative rule set used if subject doesn't comply with
 *      other - typically type checking - rules and/or alternativeEnum.
 *
 * @property TableElements|undefined $tableElements {
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
 * @property ListItems|undefined $listItems {
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
 */
class ValidationRuleSet
{
    public function __construct()
    {
    }
}
