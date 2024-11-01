<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango;

use Mandango\Document\Document;

/**
 * The identity map class.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class IdentityMap implements IdentityMapInterface
{
    private array $documents;

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct()
    {
        $this->documents = [];
    }

    /**
     * {@inheritdoc}
     */
    public function set($id, Document $document): void
    {
        $this->documents[(string) $id] = $document;
    }

    /**
     * {@inheritdoc}
     */
    public function has($id): bool
    {
        return isset($this->documents[(string) $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($id): Document
    {
        return $this->documents[(string) $id];
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->documents;
    }

    /**
     * {@inheritdoc}
     */
    public function &allByReference(): array
    {
        return $this->documents;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($id): void
    {
        unset($this->documents[(string) $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->documents = [];
    }
}
