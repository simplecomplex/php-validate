<?php

declare(strict_types=1);
/*
 * Forwards compatility really; everybody will to this once.
 * But scalar parameter type declaration is no-go until then; coercion or TypeError(?).
 */

namespace SimpleComplex\Filter;

use Psr\Log\LoggerInterface;

/**
 * Class Sanitize
 *
 * @package SimpleComplex\Filter
 */
class Sanitize
{
    /**
     * @see GetInstanceTrait
     *
     * List of previously instantiated objects, by name.
     * @protected
     * @static
     * @var array $instances
     *
     * Reference to last instantiated instance.
     * @protected
     * @static
     * @var static $lastInstance
     *
     * Get previously instantiated object or create new.
     * @public
     * @static
     * @see GetInstanceTrait::getInstance()
     *
     * Kill class reference(s) to instance(s).
     * @public
     * @static
     * @see GetInstanceTrait::flushInstance()
     */
    use GetInstanceTrait;

    /**
     * For logger 'type' context; like syslog RFC 5424 'facility code'.
     *
     * @var string
     */
    const LOG_TYPE = 'sanitize';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * When provided with a logger, sanitize methods will fail gracefully
     * when given secondary argument(s) - otherwise they throw exception.
     *
     * @param LoggerInterface|null
     *  PSR-3 logger, if any.
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param LoggerInterface|null
     *  PSR-3 logger, if any.
     *
     * @return static
     */
    public static function make($logger = null)
    {
        // Make IDE recognize child class.
        /** @var Sanitize */
        return new static($logger);
    }

    /**
     * Remove tags, escape HTML entities, and remove invalid UTF-8 sequences.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return string
     */
    public function plainText($var) : string
    {
        return htmlspecialchars(strip_tags('' . $var), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Full ASCII; 0-127.
     *
     * @throws RuntimeException
     *  If native regex function fails.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return string
     */
    public function ascii($var) : string
    {
        $s = preg_replace('/[^[:ascii:]]/', '', '' . $var);
        if (!$s && $s === null) {
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new RuntimeException('Arg ' . $msg);
        }
        return $s;
    }

    /**
     * ASCII except lower ASCII and DEL.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return string
     */
    public function asciiPrintable($var) : string
    {
        return str_replace(
            chr(127),
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH)
        );
    }

    /**
     * ASCII printable that allows newline and (default) carriage return.
     *
     * @throws RuntimeException
     *  If native regex function fails.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param boolean $noCarriageReturn
     *
     * @return string
     */
    public function asciiMultiLine($var, $noCarriageReturn = false) : string
    {
        // Remove lower ASCII except newline \x0A and CR \x0D,
        // and remove DEL and upper range.
        $s = preg_replace(
            !$noCarriageReturn ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x09\x0B-\x1F\x7F]/',
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH)
        );
        if (!$s && $s === null) {
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new RuntimeException('Arg ' . $msg);
        }
        return $s;
    }

    /**
     * Allows anything but lower ASCII and DEL.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return string
     */
    public function unicodePrintable($var) : string
    {
        return str_replace(
            chr(127),
            '',
            filter_var('' . $var, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)
        );
    }

    /**
     * Unicode printable that allows newline and (default) carriage return.
     *
     * @throws RuntimeException
     *  If native regex function fails.
     *
     * @param mixed $var
     *  Gets stringified.
     * @param boolean $noCarriageReturn
     *
     * @return string
     */
    public function unicodeMultiline($var, $noCarriageReturn = false) : string
    {
        // Remove lower ASCII except newline \x0A and CR \x0D, and remove DEL.
        $s = preg_replace(
            !$noCarriageReturn ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x09\x0B-\x1F\x7F]/',
            '',
            '' . $var
        );
        if (!$s && $s === null) {
            $msg = 'var made native regex function fail.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new RuntimeException('Arg ' . $msg);
        }
        return $s;
    }

    /**
     * Convert number to string avoiding E-notation for numbers outside system
     * precision range.
     *
     * @throws InvalidArgumentException
     *  If arg var isn't integer/float nor number-like when stringified.
     *
     * @param mixed $var
     *  Gets stringified.
     *
     * @return string
     */
    public function numberToString($var) : string
    {
        static $precision;
        if (!$precision) {
            $precision = pow(10, (int)ini_get('precision'));
        }
        $v = '' . $var;
        if (!is_numeric($v)) {
            $msg = 'var is not integer/float nor number-like when stringified.';
            if ($this->logger) {
                $this->logger->error(get_called_class() . '->' . __FUNCTION__ . '() arg ' . $msg, [
                    'type' => static::LOG_TYPE,
                    'variable' => [
                        'var' => $var,
                    ],
                ]);
            }
            throw new InvalidArgumentException('Arg ' . $msg);
        }

        // If within system precision, just string it.
        return ($v > -$precision && $v < $precision) ? $v : number_format($v, 0, '.', '');
    }
}
