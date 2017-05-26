<?php

declare(strict_types=1);
/*
 * Scalar parameter type declaration is a no-go until everything is strict (coercion or TypeError?).
 */

namespace SimpleComplex\Filter;

use Psr\Log\AbstractLogger;

/**
 * Mock PSR-3 logger.
 *
 * @package SimpleComplex\Filter
 */
class MockLogger extends AbstractLogger
{
    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
    }
}
