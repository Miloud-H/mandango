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
 * UnitOfWork.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class UnitOfWork implements UnitOfWorkInterface
{
    private Mandango $mandango;
    private array $persist;
    private array $remove;

    /**
     * Constructor.
     *
     * @param Mandango $mandango The mandango.
     *
     * @api
     */
    public function __construct(Mandango $mandango)
    {
        $this->mandango = $mandango;
        $this->persist = [];
        $this->remove = [];
    }

    /**
     * Returns the mandango.
     *
     * @return Mandango The mandango.
     *
     * @api
     */
    public function getMandango(): Mandango
    {
        return $this->mandango;
    }

    /**
     * {@inheritdoc}
     */
    public function persist(array|Document $documents): void
    {
        if (!is_array($documents)) {
            $documents = array($documents);
        }

        foreach ($documents as $document) {
            $class = get_class($document);
            $oid = spl_object_hash($document);

            if (isset($this->remove[$class][$oid])) {
                unset($this->remove[$class][$oid]);
            }

            $this->persist[$class][$oid] = $document;
        }
    }

    /**
     * Returns if a document is pending for persist.
     *
     * @param Document $document A document.
     *
     * @return bool If the document is pending for persist.
     *
     * @api
     */
    public function isPendingForPersist(Document $document): bool
    {
        return isset($this->persist[get_class($document)][spl_object_hash($document)]);
    }

    /**
     * Returns if there are pending persist operations.
     *
     * @return boolean If there are pending persist operations.
     *
     * @api
     */
    public function hasPendingForPersist(): bool
    {
        return (bool) count($this->persist);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(array|Document $documents): void
    {
        if (!is_array($documents)) {
            $documents = [$documents];
        }

        foreach ($documents as $document) {
            $class = get_class($document);
            $oid = spl_object_hash($document);

            if (isset($this->persist[$class][$oid])) {
                unset($this->persist[$class][$oid]);
            }

            $this->remove[$class][$oid] = $document;
        }
    }

    /**
     * Returns if a document is pending for remove.
     *
     * @param Document $document A document.
     *
     * @return bool If the document is pending for remove.
     *
     * @api
     */
    public function isPendingForRemove(Document $document): bool
    {
        return isset($this->remove[get_class($document)][spl_object_hash($document)]);
    }

    /**
     * Returns if there are pending remove operations.
     *
     * @return boolean If there are pending remove operations.
     *
     * @api
     */
    public function hasPendingForRemove(): bool
    {
        return (bool) count($this->remove);
    }

    /**
     * Returns if there are pending operations.
     *
     * @return boolean If there are pending operations.
     *
     * @api
     */
    public function hasPending(): bool
    {
        return $this->hasPendingForPersist() || $this->hasPendingForRemove();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        // execute
        foreach ($this->persist as $class => $documents) {
            $this->mandango->getRepository($class)->save($documents);
        }
        foreach ($this->remove as $class => $documents) {
            $this->mandango->getRepository($class)->delete($documents);
        }

        // clear
        $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->persist = [];
        $this->remove = [];
    }
}
