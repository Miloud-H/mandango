<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Tests;

use Mandango\DataLoader;
use Mandango\Mandango;
use RuntimeException;

class DataLoaderTest extends TestCase
{
    public function testConstructor()
    {
        $dataLoader = new DataLoader($this->mandango);
        $this->assertSame($this->mandango, $dataLoader->getMandango());
    }

    public function testSetGetMandango()
    {
        $dataLoader = new DataLoader($this->mandango);
        $dataLoader->setMandango($mandango = new Mandango($this->metadataFactory, $this->cache));
        $this->assertSame($mandango, $dataLoader->getMandango());
    }

    public function testLoad()
    {
        $data = [
            'Model\Article' => [
                'article_1' => [
                    'title'   => 'Article 1',
                    'content' => 'Contuent',
                    'author'  => 'sormes',
                    'categories' => [
                        'category_2',
                        'category_3',
                    ],
                ],
                'article_2' => [
                    'title' => 'My Article 2',
                ],
            ],
            'Model\Author' => [
                'pablodip' => [
                    'name' => 'PabloDip',
                ],
                'sormes' => [
                    'name' => 'Francisco',
                ],
                'barbelith' => [
                    'name' => 'Pedro',
                ],
            ],
            'Model\Category' => [
                'category_1' => [
                    'name' => 'Category1',
                ],
                'category_2' => [
                    'name' => 'Category2',
                ],
                'category_3' => [
                    'name' => 'Category3',
                ],
                'category_4' => [
                    'name' => 'Category4',
                ],
            ],
        ];

        $dataLoader = new DataLoader($this->mandango);
        $dataLoader->load($data);

        // articles
        $this->assertSame(2, $this->mandango->getRepository('Model\Article')->count());

        $article = $this->mandango->getRepository('Model\Article')->createQuery(array('title' => 'Article 1'))->one();
        $this->assertNotNull($article);
        $this->assertSame('Contuent', $article->getContent());
        $this->assertSame('Francisco', $article->getAuthor()->getName());
        $this->assertSame(2, count($article->getCategories()->getSaved()));

        $article = $this->mandango->getRepository('Model\Article')->createQuery(array('title' => 'My Article 2'))->one();
        $this->assertNotNull($article);
        $this->assertNull($article->getAuthorId());

        // authors
        $this->assertSame(3, $this->mandango->getRepository('Model\Author')->count());

        $author = $this->mandango->getRepository('Model\Author')->createQuery(array('name' => 'PabloDip'))->one();
        $this->assertNotNull($author);

        $author = $this->mandango->getRepository('Model\Author')->createQuery(array('name' => 'Francisco'))->one();
        $this->assertNotNull($author);

        $author = $this->mandango->getRepository('Model\Author')->createQuery(array('name' => 'Pedro'))->one();
        $this->assertNotNull($author);

        // categories
        $this->assertSame(4, $this->mandango->getRepository('Model\Category')->count());
    }

    public function testLoadSingleInheritanceReferences()
    {
        $data = array(
            'Model\Author' => array(
                'pablodip' => array(
                    'name' => 'pablodip',
                ),
                'barbelith' => array(
                    'name' => 'barbelith',
                ),
            ),
            'Model\Category' => array(
                'mongodb' => array(
                    'name' => 'MongoDB',
                ),
                'php' => array(
                    'name' => 'PHP',
                ),
                'performance' => array(
                    'name' => 'Performance'
                ),
            ),
            'Model\RadioFormElement' => array(
                'radio_1' => array(
                    'author' => 'pablodip',
                    'categories' => array('mongodb', 'php'),
                ),
            ),
        );

        $dataLoader = new DataLoader($this->mandango);
        $dataLoader->load($data);

        $this->assertSame(1, $this->mandango->getRepository('Model\RadioFormElement')->createQuery()->count());
        $radio = $this->mandango->getRepository('Model\RadioFormElement')->createQuery()->one();
        $this->assertSame($this->mandango->getRepository('Model\Author')->createQuery(array('name' => 'pablodip'))->one(), $radio->getAuthor());
        $this->assertSame(2, count($radio->getCategories()->getSaved()));
    }

    public function testLoadPrune()
    {
        foreach ($this->mandango->getConnections() as $connection) {
            $connection->getMongoDB()->drop();
        }

        $data = array(
            'Model\Author' => array(
                'pablodip' => array(
                    'name' => 'Pablo',
                ),
            ),
        );

        $dataLoader = new DataLoader($this->mandango);

        $dataLoader->load($data);
        $this->assertSame(1, $this->mandango->getRepository('Model\Author')->count());

        $dataLoader->load($data);
        $this->assertSame(2, $this->mandango->getRepository('Model\Author')->count());

        $dataLoader->load($data, false);
        $this->assertSame(3, $this->mandango->getRepository('Model\Author')->count());

        $dataLoader->load($data, true);
        $this->assertSame(1, $this->mandango->getRepository('Model\Author')->count());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testLoadMandangoUnitOfWorkHasPending()
    {
        $author = $this->mandango->create('Model\Author');
        $this->mandango->persist($author);

        $dataLoader = new DataLoader($this->mandango);
        $dataLoader->load(array(
            'Model\Author' => array(
                'barbelith' => array(
                    'name' => 'Pedro',
                ),
            ),
        ));
    }
}
