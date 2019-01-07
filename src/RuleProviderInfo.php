<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2018 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

use SimpleComplex\Utils\Dependency;
use SimpleComplex\Validate\Interfaces\RuleProviderInterface;

/**
 * Helper object for ValidationRuleSet, holding the rule provider and additional
 * info about that.
 *
 * Why not simply set rule-methods and type-methods lists on Validate object?
 * --------------------------------------------------------------------------
 * The properties would have to be public. Public properties on a Validate
 * object is not acceptable.
 *
 * @see ValidationRuleSet
 * @see ValidateAgainstRuleSet
 *
 * @uses-dependency-container validate
 *
 * @package SimpleComplex\Validate
 */
class RuleProviderInfo
{
    /**
     * @var array
     */
    protected static $ruleMethodsByClass = [];

    /**
     * @var array
     */
    protected static $typeMethodsByClass = [];

    /**
     * @var RuleProviderInterface
     */
    public $ruleProvider;

    /**
     * List of the rule provider's public methods except those methods known as
     * non-rule methods.
     *
     * Has to be set on other class than Validate,
     * because Validate can 'see' it's own protected methods.
     *
     * @see Validate::NON_RULE_METHODS
     *
     * @var string[]
     */
    public $ruleMethods;

    /**
     * List of rule methods that explicitly promise to check the subject's type.
     *
     * @see Validate::TYPE_METHODS
     *
     * @var string[]
     */
    public $typeMethods;

    /**
     * RuleProviderInfo constructor.
     *
     * @param RuleProviderInterface|null $ruleProvider
     */
    public function __construct(RuleProviderInterface $ruleProvider = null)
    {
        $this->ruleProvider = $ruleProvider ?? $this->getRuleProviderDefault();

        $class_provider = get_class($this->ruleProvider);
        if (isset(static::$ruleMethodsByClass[$class_provider])) {
            $this->ruleMethods = static::$ruleMethodsByClass[$class_provider];
            $this->typeMethods = static::$typeMethodsByClass[$class_provider];
        }
        else {
            // Has to be set on other class than Validate,
            // because Validate can 'see' it's own protected methods.
            $this->ruleMethods = static::$ruleMethodsByClass[$class_provider] = array_diff(
                get_class_methods($class_provider),
                $this->ruleProvider->getNonRuleMethods()
            );
            $this->typeMethods = static::$typeMethodsByClass[$class_provider] = $this->ruleProvider->getTypeMethods();
        }
    }

    /**
     * Get default rule provider from dependency injection container
     * or first instance of the Validate class.
     *
     * @uses-dependency-container validate
     *
     * @return RuleProviderInterface
     */
    public function getRuleProviderDefault() : RuleProviderInterface
    {
        $container = Dependency::container();
        return $container->has('validate') ? $container->get('validate') : Validate::getInstance();
    }
}
