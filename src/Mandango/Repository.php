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
use MongoCode;
use MongoCollection;
use MongoCursor;
use MongoDB;
use RuntimeException;

/**
 * The base class for repositories.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
abstract class Repository
{
    /*
     * Setted by the generator.
     */
    protected string $documentClass;
    protected bool $isFile;
    protected ?string $connectionName;
    protected string $collectionName;

    private Mandango $mandango;
    private IdentityMapInterface $identityMap;
    private ?ConnectionInterface $connection;
    private ?MongoCollection $collection;

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
        $this->identityMap = new IdentityMap();
    }

    /**
     * Returns the Mandango.
     *
     * @return Mandango The Mandango.
     *
     * @api
     */
    public function getMandango(): Mandango
    {
        return $this->mandango;
    }

    /**
     * Returns the identity map.
     *
     * @return IdentityMapInterface The identity map.
     *
     * @api
     */
    public function getIdentityMap(): IdentityMapInterface
    {
        return $this->identityMap;
    }

    /**
     * Returns the document class.
     *
     * @return string The document class.
     *
     * @api
     */
    public function getDocumentClass(): string
    {
        return $this->documentClass;
    }

    /**
     * Returns the metadata.
     *
     * @return array The metadata.
     *
     * @api
     */
    public function getMetadata(): array
    {
        return $this->mandango->getMetadataFactory()->getClass($this->documentClass);
    }

    /**
     * Returns if the document is a file (if it uses GridFS).
     *
     * @return boolean If the document is a file.
     *
     * @api
     */
    public function isFile(): bool
    {
        return $this->isFile;
    }

    /**
     * Returns the connection name, or null if it is the default.
     *
     * @return string|null The connection name.
     *
     * @api
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * Returns the collection name.
     *
     * @return string The collection name.
     *
     * @api
     */
    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    /**
     * Returns the connection.
     *
     * @return ConnectionInterface The connection.
     *
     * @api
     */
    public function getConnection(): ConnectionInterface
    {
        if (!$this->connection) {
            if ($this->connectionName) {
                $this->connection = $this->mandango->getConnection($this->connectionName);
            } else {
                $this->connection = $this->mandango->getDefaultConnection();
            }
        }

        return $this->connection;
    }

    /**
     * Returns the collection.
     *
     * @api
     */
    public function getCollection(): ?MongoCollection
    {
        if (!$this->collection) {
            // gridfs
            if ($this->isFile) {
                $this->collection = $this->getConnection()->getMongoDB()->getGridFS($this->collectionName);
            // normal
            } else {
                $this->collection = $this->getConnection()->getMongoDB()->selectCollection($this->collectionName);
            }
        }

        return $this->collection;
    }

    /**
     * Create a query for the repository document class.
     *
     * @param array $criteria The criteria for the query (optional).
     *
     * @return Query The query.
     *
     * @api
     */
    public function createQuery(array $criteria = []): Query
    {
        $class = $this->documentClass.'Query';
        $query = new $class($this);
        $query->criteria($criteria);

        return $query;
    }

    /**
     * Converts an id to use in Mongo.
     *
     * @param mixed $id An id.
     *
     * @return mixed The id to use in Mongo.
     */
    abstract public function idToMongo($id);

    /**
     * Converts an array of ids to use in Mongo.
     *
     * @param array $ids An array of ids.
     *
     * @return array The array of ids converted.
     */
    public function idsToMongo(array $ids): array
    {
        foreach ($ids as &$id) {
            $id = $this->idToMongo($id);
        }

        return $ids;
    }

    /**
     * Find documents by id.
     *
     * @param array $ids An array of ids.
     *
     * @return array An array of documents.
     *
     * @api
     */
    public function findById(array $ids): array
    {
        $mongoIds = $this->idsToMongo($ids);
        $cachedDocuments = $this->findCachedDocuments($mongoIds);

        if ($this->areAllDocumentsCached($cachedDocuments, $mongoIds)) {
            return $cachedDocuments;
        }

        $idsToQuery = $this->getIdsToQuery($cachedDocuments, $mongoIds);
        $queriedDocuments = $this->queryDocumentsByIds($idsToQuery);

        return array_merge($cachedDocuments, $queriedDocuments);
    }

    private function findCachedDocuments($mongoIds): array
    {
        $documents = [];
        foreach ($mongoIds as $id) {
            if ($this->identityMap->has($id)) {
                $documents[(string) $id] = $this->identityMap->get($id);
            }
        }

        return $documents;
    }

    private function areAllDocumentsCached($cachedDocuments, $mongoIds): bool
    {
        return count($cachedDocuments) == count($mongoIds);
    }

    private function getIdsToQuery($cachedDocuments, $mongoIds): array
    {
        $ids = [];
        foreach ($mongoIds as $id) {
            if (!isset($cachedDocuments[(string) $id])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function queryDocumentsByIds($ids): array
    {
        $criteria = ['_id' => ['$in' => $ids]];

        return $this->createQuery($criteria)->all();
    }

    /**
     * Returns one document by id.
     *
     * @param mixed $id An id.
     *
     * @return Document|null The document or null if it does not exist.
     *
     * @api
     */
    public function findOneById($id): ?Document
    {
        $id = $this->idToMongo($id);

        if ($this->identityMap->has($id)) {
            return $this->identityMap->get($id);
        }

        return $this->createQuery(['_id' => $id])->one();
    }

    /**
     * Count documents.
     *
     * @param array $query The query (opcional, by default an empty array).
     *
     * @return integer The number of documents.
     *
     * @api
     */
    public function count(array $query = []): int
    {
        return $this->getCollection()->count($query);
    }

    /**
     * Updates documents.
     *
     * @param array $query     The query.
     * @param array $newObject The new object.
     * @param array $options   The options for the update operation (optional).
     */
    public function update(array $query, array $newObject, array $options = array()): bool
    {
        return $this->getCollection()->update($query, $newObject, $options);
    }

    /**
     * Remove documents.
     *
     * @param array $query   The query (optional, by default an empty array).
     * @param array $options The options for the remove operation (optional).
     *
     * @api
     */
    public function remove(array $query = [], array $options = []): bool|array
    {
        return $this->getCollection()->remove($query, $options);
    }

    /**
     * Shortcut to the collection group method.
     *
     * @param mixed $keys    The keys.
     * @param array $initial The initial value.
     * @param mixed $reduce  The reduce function.
     * @param array $options The options (optional).
     *
     * @return array The result
     *
     * @see MongoCollection::group
     *
     * @api
     */
    public function group($keys, array $initial, $reduce, array $options = []): array
    {
        return $this->getCollection()->group($keys, $initial, $reduce, $options);
    }

    /**
     * Shortcut to make a distinct command.
     *
     * @param string $field The field.
     * @param array  $query The query (optional).
     *
     * @return array The results.
     *
     * @api
     */
    public function distinct(string $field, array $query = array()): array
    {
        return $this->getCollection()->distinct($field, $query);
    }

    /**
     * Shortcut to make map reduce.
     *
     * @param mixed $map     The map function.
     * @param mixed $reduce  The reduce function.
     * @param array $out     The out.
     * @param array $query   The query (optional).
     * @param array $options Extra options for the command (optional).
     *
     * @throws RuntimeException If the database returns an error.
     */
    public function mapReduce($map, $reduce, array $out, array $query = [], array $command = [], array $options = array()): MongoCursor
    {
        $command = array_merge($command, [
            'mapreduce' => $this->getCollectionName(),
            'map'       => is_string($map) ? new MongoCode($map) : $map,
            'reduce'    => is_string($reduce) ? new MongoCode($reduce) : $reduce,
            'out'       => $out,
            'query'     => $query,
        ]);

        $result = $this->command($command, $options);

        if (!$result['ok']) {
            throw new RuntimeException($result['errmsg']);
        }

        if (isset($out['inline']) && $out['inline']) {
            return $result['results'];
        }

        return $this->getMongoDB()->selectCollection($result['result'])->find();
    }

    private function command($command, $options = []): array
    {
        return $this->getMongoDB()->command($command, $options);
    }

    private function getMongoDB(): MongoDB
    {
        return $this->getConnection()->getMongoDB();
    }
}
