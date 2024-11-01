<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Type;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Container of types.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class Container
{
    static private array $map = [
        'bin_data'       => BinDataType::class,
        'boolean'        => BooleanType::class,
        'date'           => DateType::class,
        'float'          => FloatType::class,
        'integer'        => IntegerType::class,
        'raw'            => RawType::class,
        'serialized'     => SerializedType::class,
        'string'         => StringType::class,
    ];

    static private array $types = [];

    /**
     * Returns if exists a type by name.
     *
     * @param string $name The type name.
     *
     * @return bool Returns if the type exists.
     *
     * @api
     */
    static public function has(string $name): bool
    {
        return isset(static::$map[$name]);
    }

    /**
     * Add a type.
     *
     * @param string $name The type name.
     * @param string $class The type class.
     *
     * @throws ReflectionException
     * @api
     */
    static public function add(string $name, string $class): void
    {
        if (static::has($name)) {
            throw new InvalidArgumentException(sprintf('The type "%s" already exists.', $name));
        }

        $r = new ReflectionClass($class);
        if (!$r->isSubclassOf('Mandango\Type\Type')) {
            throw new InvalidArgumentException(sprintf('The class "%s" is not a subclass of Mandango\Type\Type.', $class));
        }

        static::$map[$name] = $class;
    }

    /**
     * Returns a type.
     *
     * @param string $name The type name.
     *
     * @return Type The type.
     *
     * @throws InvalidArgumentException If the type does not exists.
     *
     * @api
     */
    static public function get(string $name): Type
    {
        if (!isset(static::$types[$name])) {
            if (!static::has($name)) {
                throw new InvalidArgumentException(sprintf('The type "%s" does not exists.', $name));
            }

            static::$types[$name] = new static::$map[$name];
        }

        return static::$types[$name];
    }

    /**
     * Remove a type.
     *
     * @param string $name The type name.
     *
     * @throws InvalidArgumentException If the type does not exists.
     *
     * @api
     */
    static public function remove(string $name): void
    {
        if (!static::has($name)) {
            throw new InvalidArgumentException(sprintf('The type "%s" does not exists.', $name));
        }

        unset(static::$map[$name], static::$types[$name]);
    }

    /**
     * Reset the types.
     *
     * @api
     */
    static public function reset(): void
    {
        static::$map = [
            'bin_data'       => BinDataType::class,
            'boolean'        => BooleanType::class,
            'date'           => DateType::class,
            'float'          => FloatType::class,
            'integer'        => IntegerType::class,
            'raw'            => RawType::class,
            'serialized'     => SerializedType::class,
            'string'         => StringType::class,
        ];

        static::$types = [];
    }
}
