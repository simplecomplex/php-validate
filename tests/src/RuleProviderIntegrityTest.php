<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2017-2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Tests\Validate;

use PHPUnit\Framework\TestCase;

use SimpleComplex\Validate\Interfaces\RuleProviderInterface;

use SimpleComplex\Validate\UncheckedValidator;
use SimpleComplex\Validate\Validator;
use SimpleComplex\Validate\Variants\EnumVersatileUncheckedValidator;
use SimpleComplex\Validate\Variants\EnumVersatileValidator;

/**
 * @code
 * // CLI, in document root:
backend/vendor/bin/phpunit --do-not-cache-result backend/vendor/simplecomplex/validate/tests/src/RuleProviderIntegrityTest.php
 * @endcode
 *
 * @package SimpleComplex\Tests\Validate
 */
class RuleProviderIntegrityTest extends TestCase
{
    /**
     * Names of concrete classes implementing RuleProviderInterface.
     *
     * @var string[]
     */
    protected const RULE_PROVIDERS = [
        UncheckedValidator::class,
        Validator::class,
        EnumVersatileUncheckedValidator::class,
        EnumVersatileValidator::class,
    ];

    /**
     * Skips rule provider whose constructor requires arguments.
     *
     * @see \SimpleComplex\Validate\AbstractRuleProvider::getIntegrity()
     *
     * @throws \ReflectionException
     */
    public function testAllRuleProviders()
    {
        $skipped = [];
        $integrity = [];
        foreach (static::RULE_PROVIDERS as $class) {
            // Check that constructor doesn't require arguments.
            $o_rflctn = new \ReflectionClass($class);
            $a_constructor = $o_rflctn->getConstructor();
            if ($a_constructor) {
                $m_rflctn = $o_rflctn->getMethod($a_constructor['name']);
                $required_parameters = $m_rflctn->getNumberOfRequiredParameters();
                if ($required_parameters) {
                    $skipped[] = 'Rule provider ' . $class . ' skipped because constructor requires '
                        . $required_parameters . ' parameters.';
                    continue;
                }
            }
            /** @var RuleProviderInterface $rule_provider */
            $rule_provider = new $class();
            static::assertInstanceOf(
                RuleProviderInterface::class,
                $rule_provider,
                'Class ' . $class . ' doesn\'t implement RuleProviderInterface.'
            );
            $msgs = $rule_provider->getIntegrity();
            if ($msgs) {
                $integrity[] = 'Rule provider ' . $class . ' integrity failures:'
                    . "\n- " . join("\n- ", $msgs);
            }
        }

        $msg = '';
        if ($skipped || $integrity) {
            if ($skipped && $integrity) {
                $msg = join("\n", $skipped) . "\n\n" . join("\n\n", $integrity);
            }
            elseif ($skipped) {
                $msg = join("\n", $skipped);
            }
            else {
                $msg = join("\n\n", $integrity);
            }
        }
        static::assertEmpty($msg, $msg);
    }

}
