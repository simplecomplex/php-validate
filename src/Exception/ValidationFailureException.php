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
     * Skipping empty values may be relevant when keys are column names
     * (correlating to HTTP request properties) and values are messages meant
     * for a HTTP response header.
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
     * Getting names only may be relevant when keys are column names
     * (correlating to HTTP request properties) and values are messages meant
     * for a HTTP response header.
     *
     * @return string[]
     */
    public function getFailureNames() : array
    {
        if ($this->failures) {
            $names = array_keys($this->failures);
            $net = [];
            foreach ($names as $name) {
                if ($name && !ctype_digit($name)) {
                    $net[] = $name;
                }
            }
            return $net;
        }
        return [];
    }
}
