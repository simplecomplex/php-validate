<?php

namespace SimpleComplex\Filter;

/**
 * Provides static class vars and methods for reusing instance(s).
 *
 * @package SimpleComplex\Filter
 */
trait GetInstanceTrait
{
    /**
     * List of previously instantiated objects, by name.
     *
     * @var array
     */
    protected static $instances = array();

    /**
     * Reference to last instantiated instance.
     *
     * That is: if that instance was instantiated via getInstance(),
     * or if constructor passes it's $this to this var.
     *
     * Whether constructor sets/updates this var is optional.
     * Referring an instance - that may never be used again - may well be
     * unnecessary overhead.
     * On the other hand: if the class/instance is used as a singleton, and the
     * current dependency injection pattern doesn't support calling getInstance(),
     * then constructor _should_ set/update this var.
     *
     * @var static
     */
    protected static $lastInstance;

    /**
     * Get previously instantiated object or create new.
     *
     * @code
     * // Get/create specific instance.
     * $instance = Class::getInstance('myInstance', [
     *   $someLogger,
     * ]);
     * // Get specific instance, expecting it was created earlier (say:bootstrap).
     * $instance = Class::getInstance('myInstance');
     * // Get/create any instance, supplying constructor args.
     * $instance = Class::getInstance('', [
     *   $someLogger,
     * ]);
     * // Get/create any instance, expecting constructor arg defaults to work.
     * $instance = Class::getInstance();
     * @endcode
     *
     * @param string $name
     * @param array $constructorArgs
     *
     * @return static
     */
    public static function getInstance($name = '', $constructorArgs = [])
    {
        if ($name) {
            if (isset(static::$instances[$name])) {
                return static::$instances[$name];
            }
        } elseif (static::$lastInstance) {
            return static::$lastInstance;
        }

        // Same as:
        // $nstnc = static::make(...constructor arguments...);
        static::$lastInstance = $nstnc = forward_static_call_array(
            [get_called_class(), 'make'],
            $constructorArgs
        );

        if ($name) {
            static::$instances[$name] = $nstnc;
        }
        return $nstnc;
    }

    /**
     * Kill class reference(s) to instance(s).
     *
     * @param string $name
     *  Unrefer instance by that name, if exists.
     * @param bool $last
     *  Kill reference to last instantiated object.
     * @return void
     */
    public static function flushInstance($name = '', $last = false) {
        if ($name) {
            unset(static::$instances[$name]);
        }
        if ($last) {
            static::$lastInstance = null;
        }
    }
}
