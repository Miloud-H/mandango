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

use Mandango\Archive;
use Mandango\Document\AbstractDocument;
use Mandango\Query;

/**
 * ReferenceGroup.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class ReferenceGroup extends Group
{
    /**
     * Constructor.
     *
     * @param string $documentClass The document class.
     * @param AbstractDocument $parent The parent document.
     * @param string $field  The reference field.
     *
     * @api
     */
    public function __construct(string $documentClass, AbstractDocument $parent, string $field)
    {
        parent::__construct($documentClass);

        Archive::set($this, 'parent', $parent);
        Archive::set($this, 'field', $field);
    }

    /**
     * Returns the parent document.
     *
     * @return AbstractDocument The parent document.
     *
     * @api
     */
    public function getParent(): AbstractDocument
    {
        return Archive::get($this, 'parent');
    }

    /**
     * Returns the reference field.
     *
     * @return string The reference field.
     *
     * @api
     */
    public function getField(): string
    {
        return Archive::get($this, 'field');
    }

    /**
     * {@inheritdoc}
     */
    protected function doInitializeSavedData(): array
    {
        return (array) $this->getParent()->{'get'.ucfirst($this->getField())}();
    }

    /**
     * {@inheritdoc}
     */
    protected function doInitializeSaved(array $data): array
    {
        return $this->getParent()->getMandango()->getRepository($this->getDocumentClass())->findById($data);
    }

    /**
     * Creates and returns a query to query the referenced elements.
     *
     * @api
     */
    public function createQuery(): Query
    {
        return $this->getParent()->getMandango()->getRepository($this->getDocumentClass())->createQuery([
            '_id' => ['$in' => $this->doInitializeSavedData()],
        ]);
    }
}
