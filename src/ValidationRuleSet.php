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
 * Checks integrity of non-provider rules and converts child rule sets
 * (tableElements, listItemPrototype) to ValidationRuleSets.
 *
 * Only checks integrity of arguments for the provider rule enum().
 * For all other provider rules the arguments get checked run-time
 * by the rule methods called.
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
 *      Non-ValidationRuleSet object/array will be converted
 *      to ValidationRuleSet.
 *
 *      Only declared if relevant, otherwise undefined.
 *
 *
 * @property string[]|object $blackListedKeys (tableElements.) @todo
 *      List of array|object subject element keys that are illegal. @todo
 *
 * @property string[]|object $whiteListedKeys (tableElements.) @todo
 *      List of the only array|object subject element keys that are legal,
 *      apart from keys defining (sub) rule sets. @todo
 *
 * @property bool $exclusive (tableElements.) @todo
 *      Require that array|object subject @todo
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
            unset($args);
            if (ctype_digit('' . $ruleKey)) {
                // Bucket is simply the name of a rule; key is int, value is the rule.
                $rule = $ruleValue;
                $args = true;
                // The only non-provider rules supported is 'optional';
                // the others cannot be boolean.
                // And only provider rules having no parameters are legal here.
                switch ($rule) {
                    case 'optional':
                        break;
                    case 'enum':
                        throw new InvalidRuleException(
                            'Provider validation rule[' . $rule . '] at depth[' .  $depth . ']'
                            . ' cannot be defined via numeric key and bucket value,'
                            . ' because cannot be (simple) boolean true,'
                            . ' must be array (of secondary rule method arguments).'
                        );
                    default:
                        if (in_array($rule, ValidateByRules::NON_PROVIDER_RULES)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[' . $rule . '] at depth[' .  $depth . ']'
                                . ' cannot be defined via numeric key and bucket value,'
                                . ' because cannot be (simple) boolean true,'
                                . ' must be non-scalar.'
                            );
                        }
                        if (!in_array($rule, $rule_methods)) {
                            throw new InvalidRuleException(
                                'Non-existent validation rule[' . $rule . '] at depth[' .  $depth . '],'
                                . ' was attempted to be defined via numeric key and bucket value.'
                            );
                        }
                        // Could check whether the rule method has secondary paramaters, like
                        //if ((new \ReflectionMethod($ruleProvider, $rule))->getNumberOfParameters() > 1) {
                        //    throw new InvalidRuleException('... must be array (of secondary rule method arguments).');
                        //}
                        // but doesn't know rule provider instance (nor class) here.
                }
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
                    // Allow defining alternativeEnum as nested array, because
                    // easy to confuse with the format for enum.
                    // enum formally requires to be nested, since
                    // the allowed values array is second argument
                    // to be passed to the enum() method.
                    if (is_array(reset($args))) {
                        $allowed_values = current($args);
                    } else {
                        $allowed_values =& $args;
                    }
                    // Check once and for all that allowed values are scalar|null.
                    $i = -1;
                    foreach ($allowed_values as $allowed) {
                        ++$i;
                        if ($allowed !== null && !is_scalar($allowed)) {
                            throw new InvalidRuleException(
                                'Non-provider validation rule[alternativeEnum] at depth[' .  $depth
                                . '] allowed values bucket[' . $i . '] type['
                                . (!is_object($allowed) ? gettype($allowed) : get_class($allowed))
                                . '] is not scalar or null.'
                            );
                        }
                    }
                    $this->alternativeEnum =& $allowed_values;
                    unset($allowed_values, $allowed);
                    break;

                case 'tableElements':
                case 'listItems':
                    if (is_array($args)) {
                        $args_object = (object) $args;
                    } elseif (is_object($args)) {
                        $args_object = $args;
                    } else {
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
                            $tableElements = $args_object;
                            try {
                                $index = -1;
                                foreach ($tableElements as $elementName => &$subRuleSet) {
                                    $name = $elementName;
                                    ++$index;
                                    if (!($subRuleSet instanceof ValidationRuleSet)) {
                                        if (is_array($subRuleSet) || is_object($subRuleSet)) {
                                            $subRuleSet = new static(
                                                $subRuleSet, $rule_methods, $depth + 1
                                            );
                                        } else {
                                            throw new InvalidRuleException(
                                                'Non-provider validation rule[tableElements] at depth[' .  $depth
                                                . '] element rule set type['
                                                . (!is_object($args) ? gettype($args) : get_class($args))
                                                . '] is not ValidationRuleSet|array|object.'
                                            );
                                        }
                                    }
                                }
                                unset($subRuleSet);
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
                            if ($index == -1) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[tableElements] at depth[' .  $depth . '] type['
                                    . (!is_object($args) ? gettype($args) : get_class($args))
                                    . '] is empty.'
                                );
                            }
                            $this->tableElements = $tableElements;
                            break;
                        case 'listItems':
                            // Allow only specific buckets.
                            if (array_diff(
                                $prop_keys = array_keys(get_object_vars($args_object)),
                                static::ITEM_LIST_ALLOWED_KEYS
                            )) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[listItems] at depth[' .  $depth . '] type['
                                    . (!is_object($args) ? gettype($args) : get_class($args))
                                    . '] can only contain accessible keys[' . join(', ', static::ITEM_LIST_ALLOWED_KEYS)
                                    . '], saw[' . join(', ', $prop_keys) . '].'
                                );
                            }
                            unset($prop_keys);
                            // minOccur|maxOccur must be non-negative integer.
                            $occur_keys = array('minOccur' , 'maxOccur');
                            foreach ($occur_keys as $occur_key) {
                                if (isset($args_object->{$occur_key})) {
                                    if (!is_int($args_object->{$occur_key}) || $args_object->{$occur_key} < 0) {
                                        throw new InvalidRuleException(
                                            'Non-provider validation rule[listItems] at depth[' .  $depth . '] type['
                                            . (!is_object($args) ? gettype($args) : get_class($args))
                                            . '] bucket {$occur_key} type['
                                            . (!is_object($args_object->{$occur_key}) ?
                                                gettype($args_object->{$occur_key}) :
                                                get_class($args_object->{$occur_key}))
                                            . ']'
                                            . (!is_int($args_object->{$occur_key}) ? '' :
                                                ' value[' . $args_object->{$occur_key} . ']')
                                            . ' is not non-negative integer.'
                                        );
                                    }
                                }
                            }
                            unset($occur_keys, $occur_key);
                            // Positive maxOccur cannot be less than minOccur.
                            // maxOccur:zero means no maximum occurrence.
                            if (
                                isset($args_object->maxOccur) && isset($args_object->minOccur)
                                && $args_object->maxOccur && $args_object->maxOccur < $args_object->minOccur
                            ) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[listItems] at depth[' .  $depth . '] type['
                                    . (!is_object($args) ? gettype($args) : get_class($args))
                                    . '] positive maxOccur[' . $args_object->maxOccur
                                    . '] cannot be less that minOccur[' . $args_object->minOccur . '].'
                                );
                            }
                            if (!isset($args_object->itemRuleSet)) {
                                throw new InvalidRuleException(
                                    'Non-provider validation rule[listItems] at depth[' .  $depth . '] type['
                                    . (!is_object($args) ? gettype($args) : get_class($args))
                                    . '] misses ValidationRuleSet|array|object itemRuleSet bucket.'
                                );
                            }
                            if (!($args_object->itemRuleSet instanceof ValidationRuleSet)) {
                                if (is_array($args_object->itemRuleSet) || is_object($args_object->itemRuleSet)) {
                                    $args_object->itemRuleSet = new static(
                                        $args_object->itemRuleSet, $rule_methods, $depth + 1
                                    );
                                } else {
                                    throw new InvalidRuleException(
                                        'Non-provider validation rule[listItems] at depth[' .  $depth
                                        . ' itemRuleSet bucket type['
                                        . (!is_object($args) ? gettype($args) : get_class($args))
                                        . '] is not ValidationRuleSet|array|object.'
                                    );
                                }
                            }
                            $this->listItems = $args_object;
                            break;
                    }
                    unset($args_object);
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
                        case 'enum':
                            // Allow defining enum as un-nested array, because
                            // counter-intuitive.
                            // enum formally requires to be nested, since
                            // the allowed values array is second argument
                            // to be passed to the enum() method.
                            $allowed_values = reset($args);
                            if (!is_array($allowed_values)) {
                                $allowed_values =& $args;
                            }
                            // Check once and for all that allowed values are scalar|null.
                            $i = -1;
                            foreach ($allowed_values as $allowed) {
                                ++$i;
                                if ($allowed !== null && !is_scalar($allowed)) {
                                    throw new InvalidRuleException(
                                        'Validation rule[enum] at depth[' .  $depth
                                        . '] allowed values bucket[' . $i . '] type['
                                        . (!is_object($allowed) ? gettype($allowed) : get_class($allowed))
                                        . '] is not scalar or null.'
                                    );
                                }
                            }
                            $this->enum = [
                                $allowed_values
                            ];
                            unset($allowed_values, $allowed);
                            break;
                        default:
                            $this->{$rule} = $args;
                    }
            }
        }
    }

    /**
     * @var string[]
     */
    const ITEM_LIST_ALLOWED_KEYS = [
        'minOccur', 'maxOccur', 'itemRuleSet'
    ];


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
