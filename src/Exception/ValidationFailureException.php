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
     * Get list of validation failures.
     *
     * Skipping empty values may for instance be relevant when keys are column
     * names (correlating to HTTP request properties) and values are messages
     * meant for a HTTP response header; and some messages are empty because
     * dupe message (two columns involved in failure).
     *
     * @param bool $skipEmptyValues
     *      True: skip items whose bucket value is empty.
     *
     * @return array
     */
    public function getFailures(bool $skipEmptyValues = false) : array
    {
        if ($this->failures) {
            if ($skipEmptyValues) {
                $net = [];
                foreach ($this->failures as $key => $val) {
                    if ($val) {
                        $net[$key] = $val;
                    }
                }
                return $net;
            }
            return $this->failures;
        }
        return [];
    }

    /**
     * Get keys of failures, excluding keys that are numeric.
     *
     * @return string[]
     */
    public function getFailureNames() : array
    {
        if ($this->failures) {
            $keys = array_keys($this->failures);
            $net = [];
            foreach ($keys as $key) {
                if ($key && !ctype_digit('' . $key)) {
                    $net[] = $key;
                }
            }
            return $net;
        }
        return [];
    }
}
