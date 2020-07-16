<?php
/**
 * SimpleComplex PHP Validate
 * @link      https://github.com/simplecomplex/php-validate
 * @copyright Copyright (c) 2020 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-validate/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Validate;

/**
 * @package SimpleComplex\Validate
 */
class Helper
{
    /**
     * @param object $object
     *
     * @return array
     */
    public static function getPublicProperties(object $object) : array
    {
        return get_object_vars($object);
    }

    /**
     * To overcome the fact that a class/object cannot within itself
     * discriminate between public and private methods.
     *
     * @param object|string $objectOrClass
     * @param bool $instanceOnly
     *      True: without static methods; expensive because uses reflection.
     *
     * @return string[]
     *
     * @throws \TypeError
     *      Arg $objectOrClass not object|string.
     * @throws \InvalidArgumentException
     *      Class (str) $objectOrClass doesn't exist.
     */
    public static function getPublicMethods($objectOrClass, bool $instanceOnly = false) : array
    {
        if (is_object($objectOrClass)) {
            $class = get_class($objectOrClass);
        }
        elseif (is_string($objectOrClass)) {
            $class = $objectOrClass;
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(
                    'Arg $objectOrClass value[' . $class . '], such class doesn\'t exist.'
                );
            }
        }
        else {
            throw new \TypeError(
                'Arg $objectOrClass type[' . static::getType($objectOrClass) . '] is not object|string.'
            );
        }
        $all = get_class_methods($class);
        if ($instanceOnly) {
            // Prevent complaints about unhandled (highly unlikely)
            // \ReflectionException.
            try {
                $statics = (new \ReflectionClass($class))
                    ->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC);
            }
            catch (\Throwable $xcptn) {
                // Unlikely because class existence checked previously.
                throw new \InvalidArgumentException(
                    'See previous.', 0, /*\ReflectionException*/ $xcptn
                );
            }
            foreach ($statics as $method) {
                array_splice($all, array_search($method->name, $all), 1);
            }
        }
        return $all;
    }

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
}
