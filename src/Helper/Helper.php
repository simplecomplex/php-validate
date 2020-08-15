<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate\Helper;

use SimpleComplex\Validate\Exception\InvalidArgumentException;
use SimpleComplex\Validate\Exception\InvalidRuleException;

/**
 * @package SimpleComplex\Validate
 */
class Helper
{
    /**
     * Get subject class name or (non-object) type.
     *
     * Counter to native gettype() this method returns:
     * - class name instead of 'object'
     * - 'float' instead of 'double'
     * - 'null' instead of 'NULL'
     *
     * Like native gettype() this method returns:
     * - 'boolean' not 'bool'
     * - 'integer' not 'int'
     * - 'resource (closed)'
     * - 'unknown type' for unknown type
     *
     * @param mixed $subject
     *
     * @return string
     */
    public static function getType($subject)
    {
        if (!is_object($subject)) {
            $type = gettype($subject);
            switch ($type) {
                case 'double':
                    return 'float';
                case 'NULL':
                    return 'null';
                default:
                    return $type;
            }
        }
        return get_class($subject);
    }

    /**
     * List class and parent classes of an object or class name.
     *
     * @param object|string $objectOrClass
     *
     * @return string[]
     *
     * @throws InvalidArgumentException
     *      Class (str) $objectOrClass doesn't exist.
     *      Arg $objectOrClass not object|string.
     */
    public static function getClassLineage($objectOrClass)
    {
        if (is_object($objectOrClass)) {
            $class = get_class($objectOrClass);
        }
        elseif (is_string($objectOrClass)) {
            $class = $objectOrClass;
            if (!class_exists($class)) {
                throw new InvalidArgumentException(
                    'Arg $objectOrClass value[' . $class . '] class doesn\'t exist.'
                );
            }
        }
        else {
            throw new InvalidArgumentException(
                'Arg $objectOrClass type[' . static::getType($objectOrClass) . '] is not object|string.'
            );
        }
        $a = [
            $class
        ];
        while (($class = get_parent_class($class))) {
            $a[] = $class;
        }
        return $a;
    }

//    /**
//     * For listing public properties within method of object self.
//     *
//     * @see get_object_vars()
//     *
//     * @param object $object
//     *
//     * @return array
//     */
//    public static function getPublicProperties(object $object) : array
//    {
//        return get_object_vars($object);
//    }

    /**
     * For listing public methods within method of object self.
     *
     * @param object|string $objectOrClass
     * @param bool $instanceOnly
     *      True: without static methods; expensive because uses reflection.
     *
     * @return string[]
     *
     * @throws InvalidArgumentException
     *      Class (str) $objectOrClass doesn't exist.
     *      Arg $objectOrClass not object|string.
     */
    public static function getPublicMethods($objectOrClass, bool $instanceOnly = false) : array
    {
        if (is_object($objectOrClass)) {
            $class = get_class($objectOrClass);
        }
        elseif (is_string($objectOrClass)) {
            $class = $objectOrClass;
            if (!class_exists($class)) {
                throw new InvalidArgumentException(
                    'Arg $objectOrClass value[' . $class . '] class doesn\'t exist.'
                );
            }
        }
        else {
            throw new InvalidArgumentException(
                'Arg $objectOrClass type[' . static::getType($objectOrClass) . '] is not object|string.'
            );
        }
        $all = get_class_methods($class);
        if ($instanceOnly) {
            // Prevent (IDE) complaints about unhandled (highly unlikely)
            // \ReflectionException.
            try {
                // Bitwise can only do AND.
                $statics = (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_STATIC);
            }
            catch (\Throwable $xcptn) {
                // Unlikely because class existence checked previously.
                throw new InvalidArgumentException(
                    'See previous.', 0, /*\ReflectionException*/ $xcptn
                );
            }
            foreach ($statics as $method) {
                $index = array_search($method->name, $all);
                if ($index !== false) {
                    array_splice($all, $index, 1);
                }
            }
        }
        return $all;
    }

    /**
     * Removes line comments that begin at line start
     * or before any code in line.
     *
     * Also remove carriage return.
     *
     * @param string $json
     * @param bool $assoc
     *
     * @return mixed
     *
     * @throws InvalidRuleException
     *      On parse failure.
     */
    public static function parseJsonString(string $json, bool $assoc = false) {
        if ($json) {
            // Remove line comments that begin at line start
            // or before any code in line.
            $json = trim(
                preg_replace(
                    '/\n[ ]*\/\/[^\n]*/m',
                    '',
                    "\n" . str_replace("\r", '', $json)
                )
            );
        }
        $parsed = json_decode($json, $assoc);
        $error = json_last_error();
        if ($error) {
            switch ($error) {
                case JSON_ERROR_NONE: $name = 'NONE'; break;
                case JSON_ERROR_DEPTH: $name = 'DEPTH'; break;
                case JSON_ERROR_STATE_MISMATCH: $name = 'STATE_MISMATCH'; break;
                case JSON_ERROR_CTRL_CHAR: $name = 'CTRL_CHAR'; break;
                case JSON_ERROR_SYNTAX: $name = 'SYNTAX'; break;
                case JSON_ERROR_UTF8: $name = 'UTF8'; break;
                case JSON_ERROR_RECURSION: $name = 'RECURSION'; break;
                case JSON_ERROR_INF_OR_NAN: $name = 'INF_OR_NAN'; break;
                case JSON_ERROR_UNSUPPORTED_TYPE: $name = 'UNSUPPORTED_TYPE'; break;
                case JSON_ERROR_INVALID_PROPERTY_NAME: $name = 'INVALID_PROPERTY_NAME'; break;
                case JSON_ERROR_UTF16: $name = 'UTF16'; break;
                default: $name = 'unknown';
            }
            throw new InvalidRuleException(
                'Failed parsing JSON, error: (' . $name . ') ' . json_last_error_msg() . '.'
            );
        }
        return $parsed;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function removeNamespace(string $name) : string
    {
        $pos = strrpos($name, '\\');
        return $pos || $pos === 0 ? substr($name, $pos + 1) : $name;
    }
}
