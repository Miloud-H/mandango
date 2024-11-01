<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Twig;

use Mandango\Id\IdGeneratorContainer;
use Mandango\Type\Container as TypeContainer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The "mandango" extension for twig (used in the Core Mondator extension).
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class Mandango extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            'ucfirst'    => new TwigFilter('ucfirst', 'ucfirst'),
            'var_export' => new TwigFilter('var_export', 'var_export'),
        ];
    }

    public function getFunctions(): array
    {
        return [
            'mandango_id_generator'          => new TwigFunction('mandango_id_generator', [$this, 'mandangoIdGenerator']),
            'mandango_id_generator_to_mongo' => new TwigFunction('mandango_id_generator_to_mongo', [$this, 'mandangoIdGeneratorToMongo']),
            'mandango_type_to_mongo'         => new TwigFunction('mandango_type_to_mongo', [$this, 'mandangoTypeToMongo']),
            'mandango_type_to_php'           => new TwigFunction('mandango_type_to_php', [$this, 'mandangoTypeToPHP']),
        ];
    }

    public function mandangoIdGenerator($configClass, $id, $indent = 8)
    {
        $idGenerator = IdGeneratorContainer::get($configClass['idGenerator']['name']);
        $code = $idGenerator->getCode($configClass['idGenerator']['options']);
        $code = str_replace('%id%', $id, $code);
        return static::indentCode($code, $indent);
    }

    public function mandangoIdGeneratorToMongo($configClass, $id, $indent = 8)
    {
        $idGenerator = IdGeneratorContainer::get($configClass['idGenerator']['name']);
        $code = $idGenerator->getToMongoCode();
        $code = str_replace('%id%', $id, $code);
        return static::indentCode($code, $indent);
    }

    public function mandangoTypeToMongo($type, $from, $to): string
    {
        return strtr(TypeContainer::get($type)->toMongoInString(), [
            '%from%' => $from,
            '%to%'   => $to,
        ]);
    }

    public function mandangoTypeToPHP($type, $from, $to): string
    {
        return strtr(TypeContainer::get($type)->toPHPInString(), [
            '%from%' => $from,
            '%to%'   => $to,
        ]);
    }

    public function getName(): string
    {
        return 'mandango';
    }

    static private function indentCode($code, $indent)
    {
        return str_replace("\n", "\n".str_repeat(' ', $indent), $code);
    }
}
