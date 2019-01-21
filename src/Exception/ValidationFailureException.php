<?php

namespace SimpleComplex\Validate\Exception;

use SimpleComplex\Utils\Exception\UserMessageException;

/**
 * Generic validation failure exception - free to use within other packages.
 *
 * @package SimpleComplex\Validate
 */
class ValidationFailureException extends UserMessageException
{
    /**
     * @var array
     */
    protected $failures;

    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $userMessage
     * @param array|null $failures
     */
    public function __construct(
        $message = '', $code = 0, \Throwable $previous = null,
        string $userMessage = null, array $failures = null
    ) {
        parent::__construct($message, $code, $previous, $userMessage);
        if ($failures) {
            $this->failures = $failures;
        }
    }

    /**
     * @return array
     */
    public function getFailures() : array
    {
        return $this->failures ?? [];
    }
}
