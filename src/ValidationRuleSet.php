<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Dependency;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * Validation rule set.
 *
 *
 * @see Validate::challenge()
 *
 * @see simple_complex_validate_test_cli()
 *      For example of use.
 *
 *
 * @property bool|array *
 *      Provider rules are set/declared as instance vars dynamically.
 *
 *
 * @property boolean|undefined $optional
 *      Flags that the array|object subject element do not have to exist.
 *
 *      Ignored if this ruleSet is root; only valid for sub element.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @property array|undefined $alternativeEnum
 *      List of alternative valid values used if subject doesn't comply with
 *      other - typically type checking - rules.
 *
 *      Bucket values must be scalar|null.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @property array|undefined $tableElements
 *      List of array|object subject elements.
 *
 *      Each key is element name, value is a ValidationRuleSet (or array|object;
 *      then will be converted to ValidationRuleSet).
 *
 *      If no type checking rule then container() will be used.
 *
 *      tableElements + listItemPrototype is allowed.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *      Object will be cast to associative array.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 * @property ValidationRuleSet|undefined $listItemPrototype
 *      A rule set representing every element of array|object subject.
 *
 *      If no type checking rule then container() will be used.
 *
 *      tableElements + listItemPrototype is allowed.
 *      Relevant for a container derived from XML, which allows hash table
 *      elements and list items within the same container (XML sucks ;-).
 *
 *      Non-ValidationRuleSet object/array will be cast to ValidationRuleSet.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @package SimpleComplex\Validate
 */
class ValidationRuleSet
{
    /**
     * @see ValidationRuleSet::NON_PROVIDER_RULES
     */

    /**
     * Validation rule set.
     *
     * @see ValidationRuleSet::ruleMethodsAvailable()
     *
     * @param array|object $rules
     * @param array $ruleMethodsAvailable
     * @param int $depth
     *
     * @throws InvalidRuleException
     */
    public function __construct($rules, array $ruleMethodsAvailable = [], $depth = 0)
    {
        $rule_methods = $ruleMethodsAvailable ? $ruleMethodsAvailable : static::ruleMethodsAvailable();

        $rules_found = [];
        foreach ($rules as $ruleKey => $ruleValue) {
            if (ctype_digit('' . $ruleKey)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $ruleValue;
                $args = true;
            } else {
                // Bucket key is name of the rule,
                // value is arguments for the rule method.
                $rule = $ruleKey;
                $args = $ruleValue;
            }

            switch ($rule) {
                case 'optional':
                    if (!$depth && $args) {
                        $this->optional = true;
                    }
                    break;

                case 'alternativeEnum':
                    if (!$args || !is_array($args)) {
                        throw new InvalidRuleException(
                            'Non-provider validation rule[alternativeEnum] at depth[' .  $depth . '] type['
                            . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not non-empty array.'
                        );
                    }

                    // @todo: check that allowed values are scalar|null.

                    // Allow defining alternativeEnum as nested array, because
                    // easy to confuse with the format for enum.
                    // enum formally requires to be nested, since
                    // the allowed values array is second argument
                    // to be passed to the enum() method.
                    $this->alternativeEnum = is_array(reset($args)) ? reset($args) : $args;
                    break;

                case 'tableElements':
                case 'listItemPrototype':
                    $arg_type = is_array($args) ? 'array' : (is_object($args) ? 'object' : false);
                    if (!$arg_type) {
                        $msg = 'Non-provider validation rule[' . $rule
                            . '] at depth[' .  $depth . '] type['
                            . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not a array|object.';
                        $container = Dependency::container();
                        if ($container->has('logger')) {
                            if ($container->has('inspector')) {
                                $inspection = $container->get('inspector')->variable();
                            } else {
                                $inspection = 'Keys['
                                    . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
                            }
                            $container->get('logger')->warning($msg . "\n" . $inspection);
                        }
                        throw new InvalidRuleException($msg);
                    }
                    switch ($rule) {
                        case 'tableElements':
                            if ($arg_type == 'array') {
                                $args_array = $args;
                            } else {
                                $args_array = (array) $args;
                            }
                            if (!$args_array) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[tableElements] at depth[' .  $depth . '] type['
                                    . (!is_object($args) ? gettype($args) : get_class($args))
                                    . '] is empty.'
                                );
                            }
                            $tableElements = [];
                            try {
                                $index = -1;
                                foreach ($args_array as $elementName => $subRuleSet) {
                                    $name = $elementName;
                                    ++$index;
                                    if (is_array($subRuleSet)) {
                                        $tableElements[$elementName] = new static($subRuleSet);
                                    } elseif (is_object($subRuleSet)) {
                                        if ($subRuleSet instanceof ValidationRuleSet) {
                                            $tableElements[$elementName] = $subRuleSet;
                                        } else {
                                            $tableElements[$elementName] = new static(
                                                $subRuleSet, $rule_methods, $depth + 1
                                            );
                                        }
                                    } else {
                                        throw new InvalidRuleException(
                                            'Sub rule set type['
                                            . (!is_object($args) ? gettype($args) : get_class($args))
                                            . '] is not array|object.'
                                        );
                                    }
                                }
                            } catch (\Throwable $xc) {
                                $msg = 'Non-provider validation rule[tableElements] at depth[' .  $depth
                                    . '] element index[' . $index . '] name[' . $name . ']'
                                    . '] is not a valid rule set.';
                                $container = Dependency::container();
                                if ($container->has('logger')) {
                                    if ($container->has('inspector')) {
                                        $inspection = $container->get('inspector')->variable();
                                    } else {
                                        $inspection = 'Current rule set keys['
                                            . array_keys(is_array($rules) ? $rules : get_object_vars($rules)) . ']';
                                    }
                                    $container->get('logger')->warning(
                                        $msg . "\n" . $xc->getMessage() . "\n" . $inspection
                                    );
                                }
                                throw new InvalidRuleException($msg . ' ' . $xc->getMessage());
                            }
                            $this->tableElements =& $tableElements;
                            break;
                        case 'listItemPrototype':
                            if ($args instanceof ValidationRuleSet) {
                                $this->listItemPrototype = $args;
                            } else {
                                $this->listItemPrototype = new static(
                                    $args, $rule_methods, $depth + 1
                                );
                            }
                            break;
                    }
                    break;

                default:
                    // Check for dupe; 'rule':args as well as N:'rule'.
                    if (isset($rules_found[$rule])) {
                        throw new InvalidRuleException(
                            'Duplicate validation rule[' . $rule . '] at depth[' .  $depth . '].'
                        );
                    }
                    // Check rule method existance.
                    if (!in_array($rule, $rule_methods)) {
                        throw new InvalidRuleException(
                            'Non-existent validation rule[' . $rule . '] at depth[' .  $depth . '].'
                        );
                    }

                    if ($args !== true && !is_array($args)) {
                        throw new InvalidRuleException(
                            'Validation rule[' . $rule . '] at depth[' .  $depth . '] type['
                            . (!is_object($args) ? gettype($args) : get_class($args))
                            . '] is not boolean true or array.'
                        );
                    }

                    $rules_found[$rule] = true;

                    switch ($rule) {
                        // Allow defining enum as un-nested array, because
                        // counter-intuitive.
                        // enum formally requires to be nested, since
                        // the allowed values array is second argument
                        // to be passed to the enum() method.
                        case 'enum':
                            // @todo: check that allowed values are scalar|null.
                            $this->enum = !is_array(reset($args)) ? [ $args ] : $args;
                            break;
                        default:
                            $this->{$rule} = $args;
                    }
            }
        }
    }


    /**
     * List rule methods made available by a rule provider.
     *
     * Helper method. Has to be set on other class than Validate
     * (because Validate can 'see' it's own protected methods)
     * and ValidateByRules (because that's marked as internal).
     *
     * @see Validate
     *
     * @uses get_class_methods()
     * @uses Validate::getNonRuleMethods()
     *
     * @param RuleProviderInterface|null $ruleProvider
     *      Default: dependency container ID 'validator' or Validate::getInstance().
     *
     * @return array
     */
    public static function ruleMethodsAvailable(/*?RuleProviderInterface*/ $ruleProvider = null)
    {
        if (!$ruleProvider) {
            $container = Dependency::container();
            $provider = $container->has('validator') ? $container->get('validator') : Validate::getInstance();
        } else {
            $provider = $ruleProvider;
        }
        return array_diff(
            get_class_methods(get_class($provider)),
            $provider->getNonRuleMethods()
        );
    }
}
