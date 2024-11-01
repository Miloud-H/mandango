<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Logger;

use MongoCollection;
use MongoDB;

/**
 * A loggable MongoDB.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class LoggableMongoDB extends MongoDB
{
    private LoggableMongo $mongo;
    private Time $time;

    /**
     * Constructor.
     *
     * @param LoggableMongo $mongo A LoggableMongo instance.
     * @param string                         $name  The database name.
     */
    public function __construct(LoggableMongo $mongo, $name)
    {
        $this->mongo = $mongo;
        $this->time = new Time();

        return parent::__construct($mongo, $name);
    }

    /**
     * Returns the LoggableMongo.
     *
     * @return LoggableMongo The LoggableMongo.
     */
    public function getMongo(): LoggableMongo
    {
        return $this->mongo;
    }

    /**
     * Log.
     *
     * @param array $log The log.
     */
    public function log(array $log): void
    {
        $this->mongo->log(array_merge(array(
            'database' => $this->__toString()
        ), $log));
    }

    /**
     * command.
     */
    public function command(array $data, $options = []): array
    {
        $this->time->start();
        $return = parent::command($data, $options);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'command',
            'options' => $options,
            'time'    => $time,
        ]);

        return $return;
    }

    /**
     * createCollection.
     */
    public function createCollection($name, $options = false): MongoCollection
    {
        $this->time->start();
        $return = parent::createCollection($name, $options);
        $time = $this->time->stop();

        $this->log(array(
            'type'   => 'createCollection',
            'name'   => $name,
            'capped' => $options,
            'time'    => $time,
        ));

        return $return;
    }

    /**
     * createDbRef.
     */
    public function createDBRef($collection, $document_or_id): array
    {
        $this->time->start();
        $return = parent::createDBRef($collection, $document_or_id);
        $time = $this->time->stop();

        $this->log([
            'type'       => 'createDBRef',
            'collection' => $collection,
            'a'          => $document_or_id,
            'time'       => $time,
        ]);

        return $return;
    }

    /**
     * drop.
     */
    public function drop(): array
    {
        $this->time->start();
        $return = parent::drop();
        $time = $this->time->stop();

        $this->log([
            'type' => 'drop',
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * execute.
     */
    public function execute($code, array $args = []): array
    {
        $this->time->start();
        $return = parent::execute($code, $args);
        $time = $this->time->stop();

        $this->log([
            'type' => 'execute',
            'code' => $code,
            'args' => $args,
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * getDBRef.
     */
    public function getDBRef($ref): array
    {
        $this->time->start();
        $return = parent::getDBRef($ref);
        $time = $this->time->stop();

        $this->log([
            'type' => 'getDBRef',
            'ref'  => $ref,
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * listCollections.
     */
    public function listCollections($includeSystemCollections = false): array
    {
        $this->time->start();
        $return = parent::listCollections($includeSystemCollections);
        $time = $this->time->stop();

        $this->log([
            'type' => 'listCollections',
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * selectCollection.
     */
    public function selectCollection($name): LoggableMongoCollection
    {
        return new LoggableMongoCollection($this, $name);
    }

    /**
     * __get.
     */
    public function __get($name)
    {
        return $this->selectCollection($name);
    }

    /*
     * getGridFS.
     */
    public function getGridFS($prefix = 'fs'): LoggableMongoGridFS
    {
        return new LoggableMongoGridFS($this, $prefix);
    }
}
