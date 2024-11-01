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

use LogicException;
use Mandango\Logger\LoggableMongo;
use MongoClient;
use MongoDB;
use RuntimeException;

/**
 * Connection.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class Connection implements ConnectionInterface
{
    private string $server;
    private string $dbName;
    private array $options;

    private $loggerCallable;
    private ?array $logDefault;

    private ?MongoClient $mongo;
    private ?MongoDB $mongoDB;

    /**
     * Constructor.
     *
     * @param string $server  The server.
     * @param string $dbName  The database name.
     * @param array  $options The \Mongo options (optional).
     *
     * @api
     */
    public function __construct(string $server, string $dbName, array $options = [])
    {
        $this->server = $server;
        $this->dbName = $dbName;
        $this->options = $options;
    }

    /**
     * Sets the server.
     *
     * @param string $server The server.
     *
     * @throws LogicException If the mongo is initialized.
     *
     * @api
     */
    public function setServer(string $server): void
    {
        if (null !== $this->mongo) {
            throw new LogicException('The mongo is initialized.');
        }

        $this->server = $server;
    }

    /**
     * Returns the server.
     *
     * @return string $server The server.
     *
     * @api
     */
    public function getServer(): string
    {
        return $this->server;
    }

    /**
     * Sets the db name.
     *
     * @param string $dbName The db name.
     *
     * @throws LogicException If the mongoDb is initialized.
     *
     * @api
     */
    public function setDbName(string $dbName): void
    {
        if (null !== $this->mongoDB) {
            throw new LogicException('The mongoDb is initialized.');
        }

        $this->dbName = $dbName;
    }

    /**
     * Returns the database name.
     *
     * @return string The database name.
     *
     * @api
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * Sets the options.
     *
     * @param array $options An array of options.
     *
     * @throws LogicException If the mongo is initialized.
     *
     * @api
     */
    public function setOptions(array $options): void
    {
        if (null !== $this->mongo) {
            throw new LogicException('The mongo is initialized.');
        }

        $this->options = $options;
    }

    /**
     * Returns the options.
     *
     * @return array The options.
     *
     * @api
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setLoggerCallable($loggerCallable = null): void
    {
        if (null !== $this->mongo) {
            throw new RuntimeException('The connection has already Mongo.');
        }

        $this->loggerCallable = $loggerCallable;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoggerCallable()
    {
        return $this->loggerCallable;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogDefault(array $logDefault): void
    {
        if (null !== $this->mongo) {
            throw new RuntimeException('The connection has already Mongo.');
        }

        $this->logDefault = $logDefault;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDefault(): ?array
    {
        return $this->logDefault;
    }

    /**
     * {@inheritdoc}
     */
    public function getMongo(): MongoClient
    {
        if (null === $this->mongo) {
            if (null !== $this->loggerCallable) {
                $this->mongo = new LoggableMongo($this->server, $this->options);
                $this->mongo->setLoggerCallable($this->loggerCallable);
                if (null !== $this->logDefault) {
                    $this->mongo->setLogDefault($this->logDefault);
                }
            } else {
                $this->mongo = new MongoClient($this->server, $this->options);
            }
        }

        return $this->mongo;
    }

    /**
     * {@inheritdoc}
     */
    public function getMongoDB(): MongoDB
    {
        if (null === $this->mongoDB) {
            $this->mongoDB = $this->getMongo()->selectDB($this->dbName);
        }

        return $this->mongoDB;
    }
}
