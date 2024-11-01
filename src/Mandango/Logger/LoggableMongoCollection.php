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

/**
 * A loggable MongoCollection.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class LoggableMongoCollection extends MongoCollection
{
    public $db;
    private Time $time;

    /**
     * Constructor.
     *
     * @param LoggableMongoDB $db             A LoggableMongoDB instance.
     * @param string                           $collectionName The collection name.
     */
    public function __construct(LoggableMongoDB $db, $collectionName)
    {
        $this->db = $db;
        $this->time = new Time();

        parent::__construct($db, $collectionName);
    }

    /**
     * Returns the LoggableMongoDB.
     *
     * @return LoggableMongoDB The LoggableMongoDB
     */
    public function getDB(): LoggableMongoDB
    {
        return $this->db;
    }

    /**
     * Log.
     *
     * @param array $log The log.
     */
    public function log(array $log): void
    {
        $this->db->log(array_merge(array(
            'collection' => $this->getName()
        ), $log));
    }

    /**
     * count.
     */
    public function count($query = []): int
    {
        $this->time->start();
        $return = parent::count($query);
        $time = $this->time->stop();

        $this->log([
            'type'  => 'count',
            'query' => $query,
            'time'  => $time,
        ]);

        return $return;
    }

    /**
     * deleteIndex.
     */
    public function deleteIndex($keys): array
    {
        $this->time->start();
        $return = parent::deleteIndex($keys);
        $time = $this->time->stop();

        $this->log([
            'type' => 'deleteIndex',
            'keys' => $keys,
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * deleteIndexes.
     */
    public function deleteIndexes(): array
    {
        $this->time->start();
        $return = parent::deleteIndexes();
        $time = $this->time->stop();

        $this->log([
            'type' => 'deleteIndexes',
            'time' => $time,
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
     * ensureIndex.
     */
    public function ensureIndex($keys, array $options = array()): array
    {
        $this->time->start();
        $return = parent::createIndex($keys, $options);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'ensureIndex',
            'keys'    => $keys,
            'options' => $options,
            'time'    => $time,
        ]);

        return $return;
    }

    public function createIndex($keys, array $options = array()): array
    {
        $this->time->start();
        $return = parent::createIndex($keys, $options);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'createIndex',
            'keys'    => $keys,
            'options' => $options,
            'time'    => $time,
        ]);

        return $return;
    }

    /**
     * find.
     */
    public function find($query = array(), $fields = array()): LoggableMongoCursor
    {
        return new LoggableMongoCursor($this, $query, $fields);
    }

    /**
     * findOne.
     * @param array $query
     * @param array $fields
     * @param array $options
     */
    public function findOne(array $query = [], array $fields = [], array $options = []): ?array
    {
        $cursor = new LoggableMongoCursor($this, $query, $fields, 'findOne');
        $cursor->limit(-1);

        return $cursor->getNext();
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
     * getIndexInfo.
     */
    public function getIndexInfo(): array
    {
        $this->time->start();
        $return = parent::getIndexInfo();
        $time = $this->time->stop();

        $this->log([
            'type' => 'getIndexInfo',
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * group.
     */
    public function group($keys, $initial, $reduce, array $condition = array()): array
    {
        $this->time->start();
        $return = parent::group($keys, $initial, $reduce, $condition);
        $time = $this->time->stop();

        $this->log(array(
            'type'    => 'group',
            'keys'    => $keys,
            'initial' => $initial,
            'reduce'  => $reduce,
            'options' => $condition,
            'time'    => $time,
        ));

        return $return;
    }

    /**
     * insert.
     */
    public function insert($a, array $options = [])
    {
        $this->time->start();
        $return = parent::insert($a, $options);
        $time = $this->time->stop();

        $this->log(array(
            'type'    => 'insert',
            'a'       => $a,
            'options' => $options,
            'time'    => $time,
        ));

        return $return;
    }

    /**
     * remove.
     */
    public function remove($criteria = array(), array $options = array())
    {
        $this->time->start();
        $return = parent::remove($criteria, $options);
        $time = $this->time->stop();

        $this->log([
            'type'     => 'remove',
            'criteria' => $criteria,
            'options'  => $options,
            'time'     => $time,
        ]);

        return $return;
    }

    /**
     * save.
     */
    public function save($a, array $options = [])
    {
        $this->time->start();
        $return = parent::save($a, $options);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'save',
            'a'       => $a,
            'options' => $options,
            'time'    => $time,
        ]);

        return $return;
    }

    /**
     * update.
     */
    public function update($criteria, $newobj, array $options = array()): bool
    {
        $this->time->start();
        $return = parent::update($criteria, $newobj, $options);
        $time = $this->time->stop();

        $this->log([
            'type'     => 'update',
            'criteria' => $criteria,
            'newobj'   => $newobj,
            'options'  => $options,
            'time'     => $time,
        ]);

        return $return;
    }

    /**
     * validate.
     */
    public function validate($scan_data = false): array
    {
        $this->time->start();
        $return = parent::validate($scan_data);
        $time = $this->time->stop();

        $this->log(array(
            'type'     => 'validate',
            'scanData' => $scan_data,
            'time'     => $time,
        ));

        return $return;
    }
}
