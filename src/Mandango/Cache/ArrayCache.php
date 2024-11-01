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
 * ArrayCache.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class ArrayCache implements CacheInterface
{
    private array $data = [];

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->data = [];
    }
}
