<?php

declare(strict_types=1);

namespace SimpleComplex\Tests\Validate\Entity;

/**
 * Traversable class which has no pre-defined properties.
 */
class NoModelExplorable implements \Countable, \Iterator /*~ Traversable*/, \JsonSerializable
{
    /**
     * @var mixed[]
     */
    protected $properties = [];

    /**
     * Get protected property.
     *
     * @param string $key
     *
     * @return mixed
     *
     * @throws \OutOfBoundsException
     *      If no such instance property.
     */
    public function __get(string $key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $key . '].');
    }

    /**
     * Allows setting protected property unconditionally.
     *
     * @param string $key
     * @param mixed|null $value
     *
     * @return void
     */
    public function __set(string $key, $value)
    {
        $this->properties[$key] = $value;
    }

    /**
     * @param string|int $key
     *
     * @return bool
     *      False: no such property, or the value is null.
     */
    public function __isset($key) : bool
    {
        return isset($this->properties['' . $key]);
    }

    // Countable.---------------------------------------------------------------

    /**
     * @see \Countable::count()
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->properties);
    }


    // Foreachable (Iterator).--------------------------------------------------

    /**
     * @see \Iterator::rewind()
     *
     * @return void
     */
    public function rewind() : void
    {
        reset($this->properties);
    }

    /**
     * @see \Iterator::key()
     *
     * @return string
     */
    public function key() : string
    {
        return '' . key($this->properties);
    }

    /**
     * Uses __get() method to support custom initialization/retrieval.
     * @see __get()
     *
     * @see \Iterator::current()
     *
     * @return mixed
     */
    public function current()
    {
        return current($this->properties);
    }

    /**
     * @see \Iterator::next()
     *
     * @return void
     */
    public function next() : void
    {
        next($this->properties);
    }

    /**
     * @see \Iterator::valid()
     *
     * @return bool
     */
    public function valid() : bool
    {
        // The null check is cardinal; without it foreach runs out of bounds.
        return key($this->properties) !== null;
    }


    // Dumping/casting.---------------------------------------------------------

    /**
     * Dumps publicly readable properties to standard object.
     *
     * Uses __get() method to support custom initialization/retrieval.
     * @see __get()
     *
     * @param bool $recursive
     *
     * @return \stdClass
     */
    public function toObject(bool $recursive = false) : \stdClass
    {
        $o = new \stdClass();
        foreach (array_keys($this->properties) as $key) {
            $value = $this->__get($key);
            if ($recursive && $value instanceof NoModelExplorable) {
                $o->{$key} = $value->toObject(true);
            } else {
                $o->{$key} = $value;
            }
        }
        return $o;
    }

    /**
     * Dumps publicly readable properties to array.
     *
     * Uses __get() method to support custom initialization/retrieval.
     * @see __get()
     *
     * @param bool $recursive
     *
     * @return array
     */
    public function toArray(bool $recursive = false) : array
    {
        $a = [];
        foreach (array_keys($this->properties) as $key) {
            $value = $this->__get($key);
            if ($recursive && $value instanceof NoModelExplorable) {
                $a[$key] = $value->toObject(true);
            }
            else {
                $a[$key] = $value;
            }
        }
        return $a;
    }

    /**
     * Make var_dump() make sense.
     *
     * @return array
     */
    public function __debugInfo() : array
    {
        // Erring explorable property shan't make this instance un-dumpable.
        try {
            return $this->toArray(true);
        }
        catch (\Throwable $ignore) {
            return $this->toArray();
        }
    }


    // JsonSerializable.--------------------------------------------------------

    /**
     * JSON serializes to object listing all publicly readable properties.
     *
     * @return object
     */
    public function jsonSerialize()
    {
        return $this->toObject(true);
    }
}
