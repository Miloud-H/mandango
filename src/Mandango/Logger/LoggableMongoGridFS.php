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

use MongoGridFS;
use MongoGridFSFile;

/**
 * A loggable MongoGridFS.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class LoggableMongoGridFS extends MongoGridFS
{
    public $db;
    private Time $time;

    /**
     * Constructor.
     *
     * @param LoggableMongoDB $db     A LoggableMongoDB instance.
     * @param string                           $prefix The prefix (optional, fs by default).
     */
    public function __construct(LoggableMongoDB $db, $prefix = 'fs')
    {
        $this->db = $db;
        $this->time = new Time();

        parent::__construct($db, $prefix);
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
        $this->db->log(array_merge([
            'collection' => $this->getName(),
            'gridfs'     => 1,
        ], $log));
    }

    /**
     * delete.
     */
    public function delete($id): bool
    {
        $this->time->start();
        $return = parent::delete($id);
        $time = $this->time->stop();

        $this->log([
            'type' => 'delete',
            'id'   => $id,
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * get.
     */
    public function get($id): ?MongoGridFSFile
    {
        $this->time->start();
        $return = parent::get($id);
        $time = $this->time->stop();

        $this->log([
            'type' => 'get',
            'id'   => $id,
            'time' => $time,
        ]);

        return $return;
    }

    /**
     * put.
     */
    public function put($filename, array $extra = []): void
    {
        $this->time->start();
        parent::put($filename, $extra);
        $time = $this->time->stop();

        $this->log(log: array(
            'type'     => 'put',
            'filename' => $filename,
            'extra'    => $extra,
            'time'     => $time,
        ));
    }

    /**
     * storeBytes.
     */
    public function storeBytes($bytes, $extra = [], $options = [])
    {
        $this->time->start();
        $return = parent::storeBytes($bytes, $extra, $options);
        $time = $this->time->stop();

        $this->log([
            'type'       => 'storeBytes',
            'bytes_sha1' => sha1($bytes),
            'extra'      => $extra,
            'options'    => $options,
            'time'       => $time,
        ]);

        return $return;
    }

    /**
     * storeFile.
     */
    public function storeFile($filename, $extra = [], $options = [])
    {
        $this->time->start();
        $return = parent::storeFile($filename, $extra, $options);
        $time = $this->time->stop();

        $this->log([
            'type'      => 'storeFile',
            'filename'  => $filename,
            'extra'     => $extra,
            'options'   => $options,
            'time'      => $time,
        ]);

        return $return;
    }

    /**
     * storeUpload.
     */
    public function storeUpload($name, array $metadata = [])
    {
        $this->time->start();
        $return = parent::storeUpload($name, $metadata['filename']);
        $time = $this->time->stop();

        $this->log(array(
            'type'     => 'storeUpload',
            'name'     => $name,
            'filename' => $metadata['filename'],
            'time'     => $time,
        ));

        return $return;
    }

    /**
     * count.
     */
    public function count($query = array()): int
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
    public function ensureIndex($keys, array $options = []): true|array
    {
        $this->time->start();
        $return = parent::createIndex($keys, $options);
        $time = $this->time->stop();

        $this->log(array(
            'type'    => 'ensureIndex',
            'keys'    => $keys,
            'options' => $options,
            'time'    => $time,
        ));

        return $return;
    }

    public function createIndex($keys, array $options = []): true|array
    {
        $this->time->start();
        $return = parent::createIndex($keys, $options);
        $time = $this->time->stop();

        $this->log(array(
            'type'    => 'createIndex',
            'keys'    => $keys,
            'options' => $options,
            'time'    => $time,
        ));

        return $return;
    }

    /*
     * find.
     */
    public function find($query = [], $fields = []): LoggableMongoGridFSCursor
    {
        return new LoggableMongoGridFSCursor($this, $query, $fields);
    }

    /*
     * findOne.
     */
    public function findOne($query = [], $fields = []): ?MongoGridFSFile
    {
        $cursor = new LoggableMongoGridFSCursor($this, $query, $fields, 'findOne');
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
    public function group($keys, $initial, $reduce, array $condition = []): array
    {
        $this->time->start();
        $return = parent::group($keys, $initial, $reduce, $condition);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'group',
            'keys'    => $keys,
            'initial' => $initial,
            'reduce'  => $reduce,
            'options' => $condition,
            'time'    => $time,
        ]);

        return $return;
    }

    /**
     * insert.
     */
    public function insert($a, array $options = []): bool|array
    {
        $this->time->start();
        $return = parent::insert($a, $options);
        $time = $this->time->stop();

        $this->log([
            'type'    => 'insert',
            'a'       => $a,
            'options' => $options,
            'time'    => $time,
        ]);

        return $return;
    }

    /**
     * remove.
     */
    public function remove($criteria = [], array $options = []): bool
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
    public function save($a, array $options = []): bool|array
    {
        $this->time->start();
        $return = parent::save($a, $options);
        $time = $this->time->stop();

        $this->log(log: array(
            'type'    => 'save',
            'a'       => $a,
            'options' => $options,
            'time'    => $time,
        ));

        return $return;
    }

    /**
     * update.
     */
    public function update($criteria, $newobj, array $options = []): bool
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

        $this->log([
            'type'     => 'validate',
            'scanData' => $scan_data,
            'time'     => $time,
        ]);

        return $return;
    }
}
