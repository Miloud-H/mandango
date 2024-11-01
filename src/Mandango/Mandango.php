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

use InvalidArgumentException;
use Mandango\Cache\CacheInterface;
use Mandango\Document\AbstractDocument;
use Mandango\Document\Document;
use RuntimeException;

/**
 * Mandango.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class Mandango
{
    const string VERSION = '1.0.0-DEV';

    private MetadataFactory $metadataFactory;
    private CacheInterface $cache;
    private $loggerCallable;
    private UnitOfWorkInterface $unitOfWork;
    private array $connections;
    private ?string $defaultConnectionName = null;
    private array $repositories;

    /**
     * Constructor.
     *
     * @param MetadataFactory $metadataFactory The metadata factory.
     * @param CacheInterface $cache           The cache.
     * @param mixed                          $loggerCallable  The logger callable (optional, null by default).
     *
     * @api
     */
    public function __construct(MetadataFactory $metadataFactory, CacheInterface $cache, $loggerCallable = null)
    {
        $this->metadataFactory = $metadataFactory;
        $this->cache = $cache;
        $this->loggerCallable = $loggerCallable;
        $this->unitOfWork = new UnitOfWork($this);
        $this->connections = array();
        $this->repositories = array();
    }

    /**
     * Returns the metadata factory.
     *
     * @return MetadataFactory The metadata factory.
     *
     * @api
     */
    public function getMetadataFactory(): MetadataFactory
    {
        return $this->metadataFactory;
    }

    /**
     * Returns the cache.
     *
     * @return CacheInterface The cache.
     *
     * @api
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Returns the logger callable.
     *
     * @return mixed The logger callable.
     *
     * @api
     */
    public function getLoggerCallable()
    {
        return $this->loggerCallable;
    }

    /**
     * Returns the UnitOfWork.
     *
     * @return UnitOfWork The UnitOfWork.
     *
     * @api
     */
    public function getUnitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
    }

    /**
     * Set a connection.
     *
     * @param string $name       The connection name.
     * @param ConnectionInterface $connection The connection.
     *
     * @api
     */
    public function setConnection(string $name, ConnectionInterface $connection): void
    {
        if (null !== $this->loggerCallable) {
            $connection->setLoggerCallable($this->loggerCallable);
            $connection->setLogDefault(['connection' => $name]);
        } else {
            $connection->setLoggerCallable();
        }

        $this->connections[$name] = $connection;
    }

    /**
     * Set the connections.
     *
     * @param array $connections An array of connections.
     *
     * @api
     */
    public function setConnections(array $connections): void
    {
        $this->connections = [];
        foreach ($connections as $name => $connection) {
            $this->setConnection($name, $connection);
        }
    }

    /**
     * Remove a connection.
     *
     * @param string $name The connection name.
     *
     * @throws InvalidArgumentException If the connection does not exists.
     *
     * @api
     */
    public function removeConnection(string $name): void
    {
        if (!$this->hasConnection($name)) {
            throw new InvalidArgumentException(sprintf('The connection "%s" does not exists.', $name));
        }

        unset($this->connections[$name]);
    }

    /**
     * Clear the connections.
     *
     * @api
     */
    public function clearConnections(): void
    {
        $this->connections = [];
    }

    /**
     * Returns if a connection exists.
     *
     * @param string $name The connection name.
     *
     * @return boolean Returns if a connection exists.
     *
     * @api
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Return a connection.
     *
     * @param string $name The connection name.
     *
     * @return ConnectionInterface The connection.
     *
     * @throws InvalidArgumentException If the connection does not exists.
     *
     * @api
     */
    public function getConnection(string $name): ConnectionInterface
    {
        if (!$this->hasConnection($name)) {
            throw new InvalidArgumentException(sprintf('The connection "%s" does not exist.', $name));
        }

        return $this->connections[$name];
    }

    /**
     * Returns the connections.
     *
     * @return array The array of connections.
     *
     * @api
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Set the default connection name.
     *
     * @param string $name The connection name.
     *
     * @api
     */
    public function setDefaultConnectionName(string $name): void
    {
        $this->defaultConnectionName = $name;
    }

    /**
     * Returns the default connection name.
     *
     * @return string The default connection name.
     *
     * @api
     */
    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnectionName;
    }

    /**
     * Returns the default connection.
     *
     * @return ConnectionInterface The default connection.
     *
     * @throws RuntimeException If there is not default connection name.
     * @throws RuntimeException If the default connection does not exists.
     *
     * @api
     */
    public function getDefaultConnection(): ConnectionInterface
    {
        if (null === $this->defaultConnectionName) {
            throw new RuntimeException('There is not default connection name.');
        }

        if (!isset($this->connections[$this->defaultConnectionName])) {
            throw new RuntimeException(sprintf('The default connection "%s" does not exists.', $this->defaultConnectionName));
        }

        return $this->connections[$this->defaultConnectionName];
    }

    /**
     * Returns the metadata of a document class.
     *
     * @param string $documentClass The document class.
     *
     * @return array The metadata.
     *
     * @api
     */
    public function getMetadata(string $documentClass): array
    {
        return $this->metadataFactory->getClass($documentClass);
    }

    /**
     * Creates a new document.
     *
     * @param string $documentClass  The document class.
     * @param array  $initializeArgs The args to initialize method of the document (optional).
     *
     * @return Document The document.
     *
     * @api
     */
    public function create(string $documentClass, array $initializeArgs = []): AbstractDocument
    {
        $document = new $documentClass($this);
        $document->initializeDefaults();
        if (method_exists($document, 'initialize')) {
            call_user_func_array(array($document, 'initialize'), $initializeArgs);
        }

        return $document;
    }

    /**
     * Returns repositories by document class.
     *
     * @param string $documentClass The document class.
     *
     * @return Repository The repository.
     *
     * @throws InvalidArgumentException If the document class is not a valid document class.
     * @throws RuntimeException         If the repository class build does not exist.
     *
     * @api
     */
    public function getRepository(string $documentClass): Repository
    {
        if (!isset($this->repositories[$documentClass])) {
            if (!$this->metadataFactory->hasClass($documentClass) || !$this->metadataFactory->isDocumentClass($documentClass)) {
                throw new InvalidArgumentException(sprintf('The class "%s" is not a valid document class.', $documentClass));
            }

            $repositoryClass = $documentClass.'Repository';
            if (!class_exists($repositoryClass)) {
                throw new RuntimeException(sprintf('The class "%s" does not exists.', $repositoryClass));
            }

            $this->repositories[$documentClass] = new $repositoryClass($this);
        }

        return $this->repositories[$documentClass];
    }

    /**
     * Returns all repositories.
     *
     * @return array All repositories.
     *
     * @api
     */
    public function getAllRepositories(): array
    {
        foreach ($this->metadataFactory->getDocumentClasses() as $class) {
            $this->getRepository($class);
        }

        return $this->repositories;
    }

    /**
     * Ensure the indexes of all repositories.
     *
     * @api
     */
    public function ensureAllIndexes(): void
    {
        foreach ($this->getAllRepositories() as $repository) {
            $repository->ensureIndexes();
        }
    }

    /**
     * Fixes all the missing references.
     */
    public function fixAllMissingReferences($documentsPerBatch = 1000): void
    {
        foreach ($this->getAllRepositories() as $repository) {
            $repository->fixMissingReferences($documentsPerBatch);
        }
    }

    /**
     * Access to UnitOfWork ->persist() method.
     *
     * @see UnitOfWork::persist()
     *
     * @api
     */
    public function persist($documents): void
    {
        $this->unitOfWork->persist($documents);
    }

    /**
     * Access to UnitOfWork ->remove() method.
     *
     * @see UnitOfWork::remove()
     *
     * @api
     */
    public function remove($document): void
    {
        $this->unitOfWork->remove($document);
    }

    /**
     * Access to UnitOfWork ->commit() method.
     *
     * @see UnitOfWork::commit()
     *
     * @api
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
    }
}
