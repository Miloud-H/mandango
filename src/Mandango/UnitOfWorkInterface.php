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
 * UnitOfWorkInterface.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
interface UnitOfWorkInterface
{
    /**
     * Persist a document.
     *
     * @param array|Document $documents A document or an array of documents.
     *
     * @api
     */
    function persist(array|Document $documents);

    /**
     * Remove a document.
     *
     * @param array|Document $documents A document or an array of documents.
     *
     * @api
     */
    function remove(array|Document $documents);

    /**
     * Commit pending persist and remove operations.
     *
     * @api
     */
    function commit();

    /**
     * Clear the pending operations
     *
     * @api
     */
    function clear();
}
