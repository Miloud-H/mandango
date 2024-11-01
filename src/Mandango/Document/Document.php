<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Document;

use Mandango\Archive;
use Mandango\Repository;
use MongoId;

/**
 * The base class for documents.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
abstract class Document extends AbstractDocument
{
    private bool $isNew = true;
    private ?MongoId $id;

    /**
     * Returns the repository.
     *
     * @return Repository The repository.
     *
     * @api
     */
    public function getRepository(): Repository
    {
        return $this->getMandango()->getRepository(get_class($this));
    }

    /**
     * Set the id of the document.
     *
     * @return Document The document (fluent interface).
     */
    public function setId(MongoId $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Returns the id of document.
     *
     * @return MongoId|null The id of the document or null if it is new.
     *
     * @api
     */
    public function getId(): ?MongoId
    {
        return $this->id;
    }

    /**
     * INTERNAL. Returns if the document is new.
     *
     * @param Boolean $isNew If the document is new.
     *
     * @return Document The document (fluent interface).
     */
    public function setIsNew(bool $isNew): self
    {
        $this->isNew = $isNew;

        return $this;
    }

    /**
     * Returns if the document is new.
     *
     * @return bool Returns if the document is new.
     *
     * @api
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * Refresh the document data from the database.
     *
     * @return Document The document (fluent interface).
     *
     * @throws \LogicException
     *
     * @api
     */
    public function refresh(): self
    {
        if ($this->isNew()) {
            throw new \LogicException('The document is new.');
        }

        $this->setDocumentData($this->getRepository()->getCollection()->findOne(array('_id' => $this->getId())), true);

        return $this;
    }

    /**
     * Save the document.
     *
     * @param array $options The options for the batch insert or update operation, it depends on if the document is new or not (optional).
     *
     * @return Document The document (fluent interface).
     *
     * @api
     */
    public function save(array $options = []): self
    {
        if ($this->isNew()) {
            $batchInsertOptions = $options;
            $updateOptions = [];
        } else {
            $batchInsertOptions = [];
            $updateOptions = $options;
        }

        $this->getRepository()->save($this, $batchInsertOptions, $updateOptions);

        return $this;
    }

    /**
     * Delete the document.
     *
     * @param array $options The options for the remove operation (optional).
     *
     * @api
     */
    public function delete(array $options = array()): void
    {
        $this->getRepository()->delete($this, $options);
    }

    /**
     * Adds a query hash.
     *
     * @param string $hash The query hash.
     */
    public function addQueryHash($hash): void
    {
        $queryHashes =& Archive::getByRef($this, 'query_hashes', []);
        $queryHashes[] = $hash;
    }

    /**
     * Returns the query hashes.
     *
     * @return array The query hashes.
     */
    public function getQueryHashes(): array
    {
        return Archive::getOrDefault($this, 'query_hashes', []);
    }

    /**
     * Removes a query hash.
     *
     * @param string $hash The query hash.
     */
    public function removeQueryHash(string $hash): void
    {
        $queryHashes =& Archive::getByRef($this, 'query_hashes', []);
        unset($queryHashes[array_search($hash, $queryHashes)]);
        $queryHashes = array_values($queryHashes);
    }

    /**
     * Clear the query hashes.
     */
    public function clearQueryHashes(): void
    {
        Archive::remove($this, 'query_hashes');
    }

    /**
     * Add a field cache.
     */
    public function addFieldCache($field): void
    {
        $cache = $this->getMandango()->getCache();

        foreach ($this->getQueryHashes() as $hash) {
            $value = $cache->has($hash) ? $cache->get($hash) : [];
            $value['fields'][$field] = 1;
            $cache->set($hash, $value);
        }
    }

    /**
     * Adds a reference cache
     */
    public function addReferenceCache($reference): void
    {
        $cache = $this->getMandango()->getCache();

        foreach ($this->getQueryHashes() as $hash) {
            $value = $cache->has($hash) ? $cache->get($hash) : [];
            if (!isset($value['references']) || !in_array($reference, $value['references'])) {
                $value['references'][] = $reference;
                $cache->set($hash, $value);
            }
        }
    }
}
