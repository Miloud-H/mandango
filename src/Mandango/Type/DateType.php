<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Type;

use DateTime;
use MongoDate;

/**
 * DateType.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 *
 * @api
 */
class DateType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function toMongo($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->getTimestamp();
        } elseif (is_string($value)) {
            $value = strtotime($value);
        }

        return new MongoDate($value);
    }

    /**
     * {@inheritdoc}
     */
    public function toPHP($value)
    {
        $date = new DateTime();
        $date->setTimestamp($value->sec);

        return $date;
    }

    /**
     * {@inheritdoc}
     */
    public function toMongoInString(): string
    {
        return '%to% = %from%; if (%to% instanceof \DateTime) { %to% = %from%->getTimestamp(); } elseif (is_string(%to%)) { %to% = strtotime(%from%); } %to% = new \MongoDate(%to%);';
    }

    /**
     * {@inheritdoc}
     */
    public function toPHPInString(): string
    {
        return '%to% = new \DateTime(); %to%->setTimestamp(%from%->sec);';
    }
}
