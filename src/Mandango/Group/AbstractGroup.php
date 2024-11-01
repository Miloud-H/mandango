<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Group;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Mandango\Archive;
use Mandango\Document\AbstractDocument;
use Traversable;

/**
 * AbstractGroup.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
abstract class AbstractGroup implements Countable, IteratorAggregate
{
    private ?array $saved;

    /**
     * Destructor - empties the Archive cache
     */
    public function __destruct()
    {
        Archive::removeObject($this);
    }

    /**
     * Adds document/s to the add queue of the group.
     *
     * @param array|AbstractDocument $documents One or more documents.
     *
     * @api
     */
    public function add(array|AbstractDocument $documents): void
    {
        if (!is_array($documents)) {
            $documents = [$documents];
        }

        $add =& Archive::getByRef($this, 'add', []);
        foreach ($documents as $document) {
            $add[] = $document;
        }
    }

    /**
     * Returns the add queue of the group.
     *
     * @api
     */
    public function getAdd()
    {
        return Archive::getOrDefault($this, 'add', array());
    }

    /**
     * Clears the add queue of the group.
     *
     * @api
     */
    public function clearAdd(): void
    {
        Archive::remove($this, 'add');
    }

    /**
     * Adds document/s to the remove queue of the group.
     *
     * @param array|AbstractDocument $documents One of more documents.
     *
     * @api
     */
    public function remove(array|AbstractDocument $documents): void
    {
        if (!is_array($documents)) {
            $documents = array($documents);
        }

        $remove =& Archive::getByRef($this, 'remove', array());
        foreach ($documents as $document) {
            $remove[] = $document;
        }
    }

    /**
     * Returns the remove queue of the group.
     *
     * @api
     */
    public function getRemove()
    {
        return Archive::getOrDefault($this, 'remove', array());
    }

    /**
     * Clears the remove queue of the group.
     *
     * @api
     */
    public function clearRemove(): void
    {
        Archive::remove($this, 'remove');
    }

    /**
     * Returns the saved documents of the group.
     */
    public function getSaved(): array
    {
        if (null === $this->saved) {
            $this->initializeSaved();
        }

        return $this->saved;
    }

    /**
     * Returns the saved + add - removed elements.
     *
     * @api
     */
    public function all(): array
    {
        $documents = array_merge($this->getSaved(), $this->getAdd());

        foreach ($this->getRemove() as $document) {
            if (false !== $key = array_search($document, $documents)) {
                unset($documents[$key]);
            }
        }

        return array_values($documents);
    }

    /**
     * Implements the \IteratorAggregate interface.
     *
     * @api
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    /**
     * Refresh the saved documents.
     *
     * @api
     */
    public function refreshSaved(): void
    {
        $this->initializeSaved();
    }

    /**
     * Initializes the saved documents.
     */
    private function initializeSaved(): void
    {
        $this->saved = $this->doInitializeSaved($this->doInitializeSavedData());
    }

    /**
     * Clears the saved documents.
     *
     * @api
     */
    public function clearSaved(): void
    {
        $this->saved = null;
    }

    /**
     * Returns if the saved documents are initialized.
     *
     * @return bool If the saved documents are initialized.
     *
     * @api
     */
    public function isSavedInitialized(): bool
    {
        return null !== $this->saved;
    }

    /**
     * Do the initialization of the saved documents data.
     *
     * @api
     */
    abstract protected function doInitializeSavedData();

    /**
     * Do the initialization of the saved documents.
     *
     * @api
     */
    protected function doInitializeSaved(array $data): array
    {
        return $data;
    }

    /**
     * Returns the number of all documents.
     *
     * @api
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Replace all documents.
     *
     * @param array $documents An array of documents.
     *
     * @api
     */
    public function replace(array $documents): void
    {
        $this->clearAdd();
        $this->clearRemove();

        $this->remove($this->getSaved());
        $this->add($documents);
    }

    /**
     * Resets the group (clear adds and removed, and saved if there are adds or removed).
     *
     * @api
     */
    public function reset(): void
    {
        if ($this->getAdd() || $this->getRemove()) {
            $this->clearSaved();
        }
        $this->clearAdd();
        $this->clearRemove();
    }
}
