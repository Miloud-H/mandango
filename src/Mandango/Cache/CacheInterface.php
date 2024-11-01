<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Cache;

/**
 * CacheInterface.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
interface CacheInterface
{
    /**
     * Checks if the cache has a value for a key.
     *
     * @param string $key A unique key.
     *
     * @return bool Whether the cache has a key.
     */
    function has(string $key): bool;

    /**
     * Returns the value for a key.
     *
     * @param string $key A unique key.
     *
     * @return mixed The value for a key.
     */
    function get(string $key): mixed;

    /**
     * Sets a value for a key.
     *
     * @param string $key   A unique key.
     * @param mixed  $value The value.
     */
    function set(string $key, mixed $value);

    /**
     * Removes a value from the cache.
     *
     * @param string $key A unique key.
     */
    function remove(string $key);

    /**
     * Clears the cache.
     */
    function clear();
}
