<?php

/*
 * This file is part of Mandango.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Mandango\Extension;

use InvalidArgumentException;
use Mandango\Mondator\Dumper;
use Mandango\Mondator\Extension;
use Mandango\Mondator\Definition;
use Mandango\Mondator\Definition\Method;
use Mandango\Mondator\Definition\Property;
use Mandango\Mondator\Output;
use Mandango\Id\IdGeneratorContainer;
use Mandango\Type\Container as TypeContainer;
use Mandango\Twig\Mandango as MandangoTwig;
use RuntimeException;
use Twig_Environment;

/**
 * Core extension.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class Core extends Extension
{
    /**
     * {@inheritdoc}
     */
    protected function setup(): void
    {
        $this->addRequiredOptions([
            'metadata_factory_class',
            'metadata_factory_output',
        ]);

        $this->addOptions([
            'default_output'    => null,
            'default_behaviors' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function doNewClassExtensionsProcess(): void
    {
        // default behaviors
        foreach ($this->getOption('default_behaviors') as $behavior) {
            if (!empty($configClass['isEmbedded']) && !empty($behavior['not_with_embeddeds'])) {
                continue;
            }
            $this->newClassExtensions[] = $this->createClassExtensionFromArray($behavior);
        }

        // class behaviors
        if (isset($this->configClass['behaviors'])) {
            foreach ($this->configClass['behaviors'] as $behavior) {
                $this->newClassExtensions[] = $this->createClassExtensionFromArray($behavior);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doConfigClassProcess(): void
    {
        $this->initIsEmbeddedProcess();

        $this->initInheritableProcess();
        $this->initInheritanceProcess();

        $this->initMandangoProcess();
        if (!$this->configClass['isEmbedded']) {
            $this->initUseBatchInsertProcess();
            $this->initConnectionNameProcess();
            $this->initCollectionNameProcess();
        }
        $this->initIndexesProcess();

        $this->initFieldsProcess();
        $this->initReferencesProcess();
        $this->initEmbeddedsProcess();
        if (!$this->configClass['isEmbedded']) {
            $this->initRelationsProcess();
        }

        $this->initEventsProcess();
        $this->initOnDeleteProcess();
        $this->initIsFileProcess();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClassProcess(): void
    {
        // parse and check
        if (!$this->configClass['isEmbedded']) {
            $this->parseAndCheckIdGeneratorProcess();
        }
        $this->parseAndCheckFieldsProcess();
        $this->parseAndCheckReferencesProcess();
        $this->parseAndCheckEmbeddedsProcess();
        if (!$this->configClass['isEmbedded']) {
            $this->parseAndCheckRelationsProcess();
        }
        $this->checkDataNamesProcess();
        $this->parseOnDeleteProcess();

        // definitions
        $this->initDefinitionsProcess();

        // document
        $templates = [
            'DocumentInitializeDefaults',
            'DocumentSetDocumentData',
            'DocumentFields',
            'DocumentReferencesOne',
            'DocumentReferencesMany',
            'DocumentProcessOnDelete',
        ];
        if ($this->configClass['_has_references']) {
            $templates[] = 'DocumentUpdateReferenceFields';
            $templates[] = 'DocumentSaveReferences';
        }
        $templates[] = 'DocumentEmbeddedsOne';
        $templates[] = 'DocumentEmbeddedsMany';
        if (!$this->configClass['isEmbedded']) {
            $templates[] = 'DocumentRelations';
        }
        if ($this->configClass['_has_groups']) {
            $templates[] = 'DocumentResetGroups';
        }
        $templates[] = 'DocumentSetGet';
        $templates[] = 'DocumentFromToArray';
        $templates[] = 'DocumentEventsMethods';
        $templates[] = 'DocumentQueryForSave';

        foreach ($templates as $template) {
            $this->processTemplate($this->definitions['document_base'], file_get_contents(__DIR__.'/templates/Core/'.$template.'.php.twig'));
        }

        if (!$this->configClass['isEmbedded']) {
            // repository
            $this->processTemplate($this->definitions['repository_base'], file_get_contents(__DIR__.'/templates/Core/Repository.php.twig'));

            // query
            $this->processTemplate($this->definitions['query_base'], file_get_contents(__DIR__.'/templates/Core/Query.php.twig'));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doPreGlobalProcess(): void
    {
        $this->globalInheritableAndInheritanceProcess();
        $this->globalHasReferencesProcess();
        $this->globalOnDeleteProcess();
        $this->globalHasGroupsProcess();
        $this->globalIndexesProcess();
    }

    /**
     * {@inheritdoc}
     */
    protected function doPostGlobalProcess(): void
    {
        $this->globalMetadataProcess();
    }

    /*
     * configClass
     */
    private function initInheritableProcess(): void
    {
        if (!isset($this->configClass['inheritable'])) {
            $this->configClass['inheritable'] = false;
        } elseif ($this->configClass['isEmbedded']) {
            throw new RuntimeException(sprintf('Using unheritance in a embedded document "%s".', $this->class));
        }
    }

    private function initInheritanceProcess(): void
    {
        if (!isset($this->configClass['inheritance'])) {
            $this->configClass['inheritance'] = false;
        } elseif ($this->configClass['isEmbedded']) {
            throw new RuntimeException(sprintf('Using unheritance in a embedded document "%s".', $this->class));
        }
    }

    private function initIsEmbeddedProcess(): void
    {
        $this->configClass['isEmbedded'] = $this->mapArrayKeyWithDefault(
            $this->configClass, 'isEmbedded',
            array($this, 'mapToBoolean'),
            false
        );
    }

    private function initMandangoProcess(): void
    {
        if (!isset($this->configClass['mandango'])) {
            $this->configClass['mandango'] = null;
        }
    }

    private function initUseBatchInsertProcess(): void
    {
        $this->configClass['useBatchInsert'] = $this->mapArrayKeyWithDefault(
            $this->configClass,
            'useBatchInsert',
            array($this, 'mapToBoolean'),
            false
        );
    }

    private function initConnectionNameProcess(): void
    {
        if (!isset($this->configClass['connection'])) {
            $this->configClass['connection'] = '';
        }
    }

    private function initCollectionNameProcess(): void
    {
        if (!isset($this->configClass['collection'])) {
            $this->configClass['collection'] = strtolower(str_replace('\\', '_', $this->class));
        }
    }

    private function initFieldsProcess(): void
    {
        if (!isset($this->configClass['fields'])) {
            $this->configClass['fields'] = [];
        }
    }

    private function initReferencesProcess(): void
    {
        if (!isset($this->configClass['referencesOne'])) {
            $this->configClass['referencesOne'] = [];
        }
        if (!isset($this->configClass['referencesMany'])) {
            $this->configClass['referencesMany'] = [];
        }
    }

    private function initEmbeddedsProcess(): void
    {
        if (!isset($this->configClass['embeddedsOne'])) {
            $this->configClass['embeddedsOne'] = [];
        }
        if (!isset($this->configClass['embeddedsMany'])) {
            $this->configClass['embeddedsMany'] = [];
        }
    }

    private function initRelationsProcess(): void
    {
        if (!isset($this->configClass['relationsOne'])) {
            $this->configClass['relationsOne'] = [];
        }
        if (!isset($this->configClass['relationsManyOne'])) {
            $this->configClass['relationsManyOne'] = [];
        }
        if (!isset($this->configClass['relationsManyMany'])) {
            $this->configClass['relationsManyMany'] = [];
        }
        if (!isset($this->configClass['relationsManyThrough'])) {
            $this->configClass['relationsManyThrough'] = [];
        }
    }

    private function initIndexesProcess(): void
    {
        if (!isset($this->configClass['indexes'])) {
            $this->configClass['indexes'] = [];
        }
    }

    private function initEventsProcess(): void
    {
        foreach ([
            'preInsert',
            'postInsert',
            'preUpdate',
            'postUpdate',
            'preDelete',
            'postDelete',
                 ] as $event) {
            if (!isset($this->configClass['events']) || !isset($this->configClass['events'][$event])) {
                $this->configClass['events'][$event] = [];
            }
        }

        if (!isset($this->configClass['events'])) {
            $this->configClass['events'] = [];
        }
    }

    private function initOnDeleteProcess(): void
    {
        if (!isset($this->configClass['onDelete'])) {
            $this->configClass['onDelete'] = [];
        }
    }

    private function initIsFileProcess(): void
    {
        $this->configClass['isFile'] = $this->mapArrayKeyWithDefault(
            $this->configClass,
            'isFile',
            array($this, 'mapToBoolean'),
            false
        );
    }

    /*
     * class
     */
    private function parseAndCheckIdGeneratorProcess(): void
    {
        if (!isset($this->configClass['idGenerator'])) {
            $this->configClass['idGenerator'] = 'native';
        }

        if (!is_array($this->configClass['idGenerator'])) {
            if (!is_string($this->configClass['idGenerator'])) {
                throw new RuntimeException(sprintf('The idGenerator of the class "%s" is not neither an array nor a string.', $this->class));
            }

            $this->configClass['idGenerator'] = array('name' => $this->configClass['idGenerator']);
        }

        if (!isset($this->configClass['idGenerator']['options'])) {
            $this->configClass['idGenerator']['options'] = array();
        } elseif (!is_array($this->configClass['idGenerator']['options'])) {
            throw new RuntimeException(sprintf('The options key of the idGenerator of the class "%s" is not an array.', $this->class));
        }

        if (!IdGeneratorContainer::has($this->configClass['idGenerator']['name'])) {
            throw new RuntimeException(sprintf('The id generator "%s" of the class "%s" does not exist.', $this->configClass['idGenerator']['name'], $this->class));
        }
    }

    private function parseAndCheckFieldsProcess(): void
    {
        foreach ($this->configClass['fields'] as &$field) {
            if (is_string($field)) {
                $field = ['type' => $field];
            }

            if ($this->configClass['inheritance'] && !isset($field['inherited'])) {
                $field['inherited'] = false;
            }
        }
        unset($field);

        foreach ($this->configClass['fields'] as $name => &$field) {
            if (!is_array($field)) {
                throw new RuntimeException(sprintf('The field "%s" of the class "%s" is not a string or array.', $name, $this->class));
            }

            if (!isset($field['type'])) {
                throw new RuntimeException(sprintf('The field "%s" of the class "%s" does not have type.', $name, $this->class));
            }
            if (!TypeContainer::has($field['type'])) {
                throw new RuntimeException(sprintf('The type "%s" of the field "%s" of the class "%s" does not exists.', $field['type'], $name, $this->class));
            }

            if (!isset($field['dbName'])) {
                $field['dbName'] = $name;
            } elseif (!is_string($field['dbName'])) {
                throw new RuntimeException(sprintf('The dbName of the field "%s" of the class "%s" is not an string.', $name, $this->class));
            }
        }
        unset($field);
    }

    private function parseAndCheckReferencesProcess(): void
    {
        // one
        foreach ($this->configClass['referencesOne'] as $name => &$reference) {
            $this->parseAndCheckAssociationClass($reference, $name);

            if ($this->configClass['inheritance'] && !isset($reference['inherited'])) {
                $reference['inherited'] = false;
            }

            if (!isset($reference['field'])) {
                $reference['field'] = $name.'_reference_field';
            }
            $field = array('type' => 'raw', 'dbName' => $name, 'referenceField' => true);
            if (!empty($reference['inherited'])) {
                $field['inherited'] = true;
            }
            $this->configClass['fields'][$reference['field']] = $field;
        }

        // many
        foreach ($this->configClass['referencesMany'] as $name => &$reference) {
            $this->parseAndCheckAssociationClass($reference, $name);

            if ($this->configClass['inheritance'] && !isset($reference['inherited'])) {
                $reference['inherited'] = false;
            }

            if (!isset($reference['field'])) {
                $reference['field'] = $name.'_reference_field';
            }
            $field = array('type' => 'raw', 'dbName' => $name, 'referenceField' => true);
            if (!empty($reference['inherited'])) {
                $field['inherited'] = true;
            }
            $this->configClass['fields'][$reference['field']] = $field;
        }
    }

    private function parseAndCheckEmbeddedsProcess(): void
    {
        // one
        foreach ($this->configClass['embeddedsOne'] as $name => &$embedded) {
            $this->parseAndCheckAssociationClass($embedded, $name);

            if ($this->configClass['inheritance'] && !isset($embedded['inherited'])) {
                $embedded['inherited'] = false;
            }
        }

        // many
        foreach ($this->configClass['embeddedsMany'] as $name => &$embedded) {
            $this->parseAndCheckAssociationClass($embedded, $name);

            if ($this->configClass['inheritance'] && !isset($embedded['inherited'])) {
                $embedded['inherited'] = false;
            }
        }
    }

    private function parseAndCheckRelationsProcess(): void
    {
        // one
        foreach ($this->configClass['relationsOne'] as $name => &$relation) {
            $this->parseAndCheckAssociationClass($relation, $name);

            if (!isset($relation['reference'])) {
                throw new RuntimeException(sprintf('The relation one "%s" of the class "%s" does not have reference.', $name, $this->class));
            }
        }

        // many_one
        foreach ($this->configClass['relationsManyOne'] as $name => &$relation) {
            $this->parseAndCheckAssociationClass($relation, $name);

            if (!isset($relation['reference'])) {
                throw new RuntimeException(sprintf('The relation many one "%s" of the class "%s" does not have reference.', $name, $this->class));
            }
        }

        // many_many
        foreach ($this->configClass['relationsManyMany'] as $name => &$relation) {
            $this->parseAndCheckAssociationClass($relation, $name);

            if (!isset($relation['reference'])) {
                throw new RuntimeException(sprintf('The relation many many "%s" of the class "%s" does not have reference.', $name, $this->class));
            }
        }

        // many_through
        unset($relation);
        foreach ($this->configClass['relationsManyThrough'] as $name => $relation) {
            if (!is_array($relation)) {
                throw new RuntimeException(sprintf('The relation_many_through "%s" of the class "%s" is not an array.', $name, $this->class));
            }
            if (!isset($relation['class'])) {
                throw new RuntimeException(sprintf('The relation_many_through "%s" of the class "%s" does not have class.', $name, $this->class));
            }
            if (!isset($relation['through'])) {
                throw new RuntimeException(sprintf('The relation_many_through "%s" of the class "%s" does not have through.', $name, $this->class));
            }

            if (!isset($relation['local'])) {
                throw new RuntimeException(sprintf('The relation_many_through "%s" of the class "%s" does not have local.', $name, $this->class));
            }
            if (!isset($relation['foreign'])) {
                throw new RuntimeException(sprintf('The relation_many_through "%s" of the class "%s" does not have foreign.', $name, $this->class));
            }
        }
    }

    private function checkDataNamesProcess(): void
    {
        foreach (array_merge(
            array_keys($this->configClass['fields']),
            array_keys($this->configClass['referencesOne']),
            array_keys($this->configClass['referencesMany']),
            array_keys($this->configClass['embeddedsOne']),
            array_keys($this->configClass['embeddedsMany']),
            !$this->configClass['isEmbedded'] ? array_keys($this->configClass['relationsOne']) : array(),
            !$this->configClass['isEmbedded'] ? array_keys($this->configClass['relationsManyOne']) : array(),
            !$this->configClass['isEmbedded'] ? array_keys($this->configClass['relationsManyMany']) : array(),
            !$this->configClass['isEmbedded'] ? array_keys($this->configClass['relationsManyThrough']) : array()
        ) as $name) {
            if (in_array($name, array('mandango', 'repository', 'collection', 'id', 'query_for_save', 'fields_modified', 'document_data'))) {
                throw new RuntimeException(sprintf('The document cannot be a data with the name "%s".', $name));
            }
        }
    }

    private function parseOnDeleteProcess(): void
    {
        foreach ($this->configClass['onDelete'] as &$onDelete) {
            if ($onDelete['polymorphic']) {
                $referenceTypeKey = 'references'.ucfirst($onDelete['referenceType']);
                $reference = $this->configClasses[$onDelete['class']][$referenceTypeKey][$onDelete['referenceName']];
                $onDelete['discriminatorField'] = $reference['discriminatorField'];
                $onDelete['discriminatorMap'] = $reference['discriminatorMap'];
            }
        }
    }

    private function initDefinitionsProcess(): void
    {
        $classes = array('document' => $this->class);
        if (false !== $pos = strrpos($classes['document'], '\\')) {
            $documentNamespace = substr($classes['document'], 0, $pos);
            $documentClassName = substr($classes['document'], $pos + 1);
            $classes['document_base']   = $documentNamespace.'\\Base\\'.$documentClassName;
            $classes['repository']      = $documentNamespace.'\\'.$documentClassName.'Repository';
            $classes['repository_base'] = $documentNamespace.'\\Base\\'.$documentClassName.'Repository';
            $classes['query']           = $documentNamespace.'\\'.$documentClassName.'Query';
            $classes['query_base']      = $documentNamespace.'\\Base\\'.$documentClassName.'Query';
        } else {
            $classes['document_base']   = 'Base'.$classes['document'];
            $classes['repository']      = $classes['document'].'Repository';
            $classes['repository_base'] = 'Base'.$classes['document'].'Repository';
            $classes['query']           = $classes['document'].'Query';
            $classes['query_base']      = 'Base'.$classes['document'].'Query';
        }

        // document
        $dir = $this->getOption('default_output');
        if (isset($this->configClass['output'])) {
            $dir = $this->configClass['output'];
        }
        if (!$dir) {
            throw new RuntimeException(sprintf('The document of the class "%s" does not have output.', $this->class));
        }
        $output = new Output($dir);

        $this->definitions['document'] = $definition = new Definition($classes['document'], $output);
        $definition->setParentClass('\\'.$classes['document_base']);
        $definition->setDocComment(<<<EOF
/**
 * $this->class document.
 */
EOF
        );

        // document base
        $output = new Output($this->definitions['document']->getOutput()->getDir().'/Base', true);

        $this->definitions['document_base'] = $definition = new Definition($classes['document_base'], $output);
        $definition->setAbstract(true);
        if ($this->configClass['isEmbedded']) {
            $definition->setParentClass('\Mandango\Document\EmbeddedDocument');
        } else {
            if ($this->configClass['inheritance']) {
                $definition->setParentClass('\\'.$this->configClass['inheritance']['class']);
            } else {
                $definition->setParentClass('\Mandango\Document\Document');
            }
        }
        $definition->setDocComment(<<<EOF
/**
 * Base class of $this->class document.
 */
EOF
        );

        if (!$this->configClass['isEmbedded']) {
            // repository
            $dir = $this->getOption('default_output');
            if (isset($this->configClass['output'])) {
                $dir = $this->configClass['output'];
            }
            if (!$dir) {
                throw new RuntimeException(sprintf('The repository of the class "%s" does not have output.', $this->class));
            }
            $output = new Output($dir);

            $this->definitions['repository'] = $definition = new Definition($classes['repository'], $output);
            $definition->setParentClass('\\'.$classes['repository_base']);
            $definition->setDocComment(<<<EOF
/**
 * Repository of $this->class document.
 */
EOF
            );

            // repository base
            $output = new Output($this->definitions['repository']->getOutput()->getDir().'/Base', true);

            $this->definitions['repository_base'] = $definition = new Definition($classes['repository_base'], $output);
            $definition->setAbstract(true);
            $definition->setParentClass('\\Mandango\\Repository');
            $definition->setDocComment(<<<EOF
/**
 * Base class of repository of $this->class document.
 */
EOF
            );

            // query
            $dir = $this->getOption('default_output');
            if (isset($this->configClass['output'])) {
                $dir = $this->configClass['output'];
            }
            if (!$dir) {
                throw new RuntimeException(sprintf('The query of the class "%s" does not have output.', $this->class));
            }
            $output = new Output($dir);

            $this->definitions['query'] = $definition = new Definition($classes['query'], $output);
            $definition->setParentClass('\\'.$classes['query_base']);
            $definition->setDocComment(<<<EOF
/**
 * Query of $this->class document.
 */
EOF
            );

            // query base
            $output = new Output($this->definitions['query']->getOutput()->getDir().'/Base', true);

            $this->definitions['query_base'] = $definition = new Definition($classes['query_base'], $output);
            $definition->setAbstract(true);
            $definition->setParentClass('\\Mandango\\Query');
            $definition->setDocComment(<<<EOF
/**
 * Base class of query of $this->class document.
 */
EOF
            );
        }
    }

    /*
     * preGlobal
     */
    private function globalInheritableAndInheritanceProcess(): void
    {
        // inheritable
        foreach ($this->configClasses as $class => &$configClass) {
            if ($configClass['inheritable']) {
                if (!is_array($configClass['inheritable'])) {
                    throw new RuntimeException(sprintf('The inheritable configuration of the class "%s" is not an array.', $class));
                }

                if (!isset($configClass['inheritable']['type'])) {
                    throw new RuntimeException(sprintf('The inheritable configuration of the class "%s" does not have type.', $class));
                }

                if ($configClass['inheritable']['type'] != 'single') {
                    throw new RuntimeException(sprintf('The inheritable type "%s" of the class "%s" is not valid.', $configClass['inheritable']['type'], $class));
                }

                if (!isset($configClass['inheritable']['field'])) {
                    $configClass['inheritable']['field'] = 'type';
                }
                $configClass['inheritable']['values'] = [];
            }
        }

        // inheritance
        foreach ($this->configClasses as $class => &$configClass) {
            if (!$configClass['inheritance']) {
                $configClass['_parent_events'] = [
                    'preInsert'  => [],
                    'postInsert' => [],
                    'preUpdate'  => [],
                    'postUpdate' => [],
                    'preDelete'  => [],
                    'postDelete' => [],
                ];
                continue;
            }

            if (!isset($configClass['inheritance']['class'])) {
                throw new RuntimeException(sprintf('The inheritable configuration of the class "%s" does not have class.', $class));
            }
            $inheritanceClass = $configClass['inheritance']['class'];

            // inherited
            $inheritedFields = $this->configClasses[$inheritanceClass]['fields'];
            $inheritedReferencesOne = $this->configClasses[$inheritanceClass]['referencesOne'];
            $inheritedReferencesMany = $this->configClasses[$inheritanceClass]['referencesMany'];
            $inheritedEmbeddedsOne = $this->configClasses[$inheritanceClass]['embeddedsOne'];
            $inheritedEmbeddedsMany = $this->configClasses[$inheritanceClass]['embeddedsMany'];

            // inheritable
            if ($this->configClasses[$inheritanceClass]['inheritable']) {
                $inheritableClass = $inheritanceClass;
                $inheritable = $this->configClasses[$inheritanceClass]['inheritable'];
            } elseif ($this->configClasses[$inheritanceClass]['inheritance']) {
                $parentInheritance = $this->configClasses[$inheritanceClass]['inheritance'];
                do {
                    $continueSearchingInheritable = false;

                    // inherited
                    $inheritedFields = array_merge($inheritedFields, $this->configClasses[$parentInheritance['class']]['fields']);
                    $inheritedReferencesOne = array_merge($inheritedReferencesOne, $this->configClasses[$parentInheritance['class']]['referencesOne']);
                    $inheritedReferencesMany = array_merge($inheritedReferencesMany, $this->configClasses[$parentInheritance['class']]['referencesMany']);
                    $inheritedEmbeddedsOne = array_merge($inheritedEmbeddedsOne, $this->configClasses[$parentInheritance['class']]['embeddedsOne']);
                    $inheritedEmbeddedsMany = array_merge($inheritedEmbeddedsMany, $this->configClasses[$parentInheritance['class']]['embeddedsMany']);

                    if ($this->configClasses[$parentInheritance['class']]['inheritable']) {
                        $inheritableClass = $parentInheritance['class'];
                        $inheritable = $this->configClasses[$parentInheritance['class']]['inheritable'];
                    } else {
                        $continueSearchingInheritable = true;
                        $parentInheritance = $this->configClasses[$parentInheritance['class']]['inheritance'];
                    }
                } while ($continueSearchingInheritable);
            } else {
                throw new RuntimeException(sprintf('The class "%s" is not inheritable or has inheritance.', $configClass['inheritance']['class']));
            }

            // inherited fields
            foreach ($inheritedFields as &$field) {
                if (is_string($field)) {
                    $field = array('type' => $field);
                }

                $field['inherited'] = true;
            }
            unset($field);
            $configClass['fields'] = array_merge($inheritedFields, $configClass['fields']);

            // inherited referencesOne
            foreach ($inheritedReferencesOne as &$referenceOne) {
                $referenceOne['inherited'] = true;
            }
            unset($referenceOne);
            $configClass['referencesOne'] = array_merge($inheritedReferencesOne, $configClass['referencesOne']);

            $configClass['inheritance']['type'] = $inheritable['type'];

            // inherited referencesMany
            foreach ($inheritedReferencesMany as &$referenceMany) {
                $referenceMany['inherited'] = true;
            }
            unset($referenceMany);
            $configClass['referencesMany'] = array_merge($inheritedReferencesMany, $configClass['referencesMany']);

            // inherited embeddedsOne
            foreach ($inheritedEmbeddedsOne as &$embeddedOne) {
                $embeddedOne['inherited'] = true;
            }
            unset($embeddedOne);
            $configClass['embeddedsOne'] = array_merge($inheritedEmbeddedsOne, $configClass['embeddedsOne']);

            // inherited embeddedsMany
            foreach ($inheritedEmbeddedsMany as &$embeddedMany) {
                $embeddedMany['inherited'] = true;
            }
            unset($embeddedMany);
            $configClass['embeddedsMany'] = array_merge($inheritedEmbeddedsMany, $configClass['embeddedsMany']);

            // id generator (always the same as the last parent)
            $loopClass = $inheritableClass;
            do {
                if ($this->configClasses[$loopClass]['inheritance']) {
                    $loopClass = $this->configClasses[$loopClass]['inheritance']['class'];
                    $continue = true;
                } else {
                    if (isset($this->configClasses[$loopClass]['idGenerator'])) {
                        $configClass['idGenerator'] = $this->configClasses[$loopClass]['idGenerator'];
                    }
                    $continue = false;
                }
            } while($continue);

            // parent events
            $parentEvents = [
                'preInsert'  => [],
                'postInsert' => [],
                'preUpdate'  => [],
                'postUpdate' => [],
                'preDelete'  => [],
                'postDelete' => [],
            ];
            $loopClass = $inheritableClass;
            do {
                $parentEvents = array_merge_recursive($this->configClasses[$loopClass]['events'], $parentEvents);
                if ($this->configClasses[$loopClass]['inheritance']) {
                    $loopClass = $this->configClasses[$loopClass]['inheritance']['class'];
                    $continue = true;
                } else {
                    $continue = false;
                }
            } while ($continue);
            $configClass['_parent_events'] = $parentEvents;

            // type
            if ('single' == $inheritable['type']) {
                //single inheritance does not work with multiple inheritance
                if (!$this->configClasses[$configClass['inheritance']['class']]['inheritable']) {
                    throw new RuntimeException(sprintf('The single inheritance does not work with multiple inheritance (%s).', $class));
                }

                if (!isset($configClass['inheritance']['value'])) {
                    throw new RuntimeException(sprintf('The inheritance configuration in the class "%s" does not have value.', $class));
                }
                $value = $configClass['inheritance']['value'];
                if (isset($this->configClasses[$inheritableClass]['inheritable']['values'][$value])) {
                    throw new RuntimeException(sprintf('The value "%s" is in the single inheritance of the class "%s" more than once.', $value, $inheritanceClass));
                }
                $this->configClasses[$inheritableClass]['inheritable']['values'][$value] = $class;

                if (isset($this->configClasses[$inheritableClass]['inheritance']['class'])) {
                    $grandParentClass = $this->configClasses[$inheritableClass]['inheritance']['class'];
                    $this->configClasses[$grandParentClass]['inheritable']['values'][$value] = $class;
                }

                $configClass['collection'] = $this->configClasses[$inheritableClass]['collection'];
                $configClass['inheritance']['field'] = $inheritable['field'];
            }
        }
    }

    private function globalHasReferencesProcess(): void
    {
        do {
             $continue = false;
             foreach ($this->configClasses as $configClass) {
                 if (isset($configClass['_has_references'])) {
                     continue;
                 }

                 $hasReferences = false;
                 if ($configClass['referencesOne'] || $configClass['referencesMany']) {
                     $hasReferences = true;
                 }
                 foreach (array_merge($configClass['embeddedsOne'], $configClass['embeddedsMany']) as $embedded) {
                     if (!isset($this->configClasses[$embedded['class']]['_has_references'])) {
                         $continue = true;
                         continue 2;
                     }
                     if ($this->configClasses[$embedded['class']]['_has_references']) {
                         $hasReferences = true;
                     }
                 }
                 $configClass['_has_references'] = $hasReferences;
             }
         } while ($continue);
    }

    private function globalOnDeleteProcess(): void
    {
        foreach ($this->configClasses as $class => $configClass) {
            foreach ($configClass['referencesOne'] as $name => $reference) {
                $this->globalOnDeleteProcessReference($class, $name, $reference, 'one', ['unset', 'cascade']);
            }
            foreach ($configClass['referencesMany'] as $name => $reference) {
                $this->globalOnDeleteProcessReference($class, $name, $reference, 'many', ['unset']);
            }
        }
    }

    private function globalOnDeleteProcessReference($class, $name, $reference, $type, array $valid): void
    {
        if (isset($reference['onDelete'])) {
            if (!in_array($reference['onDelete'], $valid)) {
                throw new RuntimeException(sprintf('The onDelete value "%s" of the reference "%s" of the class "%s" is not valid.', $reference['onDelete'], $name, $class));
            }

            $onDelete = array(
                'class'         => $class,
                'referenceName' => $name,
                'referenceType' => $type,
                'polymorphic'   => !empty($reference['polymorphic']),
                'type'          => $reference['onDelete'],
            );

            if (!empty($reference['class'])) {
                $this->configClasses[$reference['class']]['onDelete'][] = $onDelete;
            } elseif (!empty($reference['polymorphic'])) {
                if (!empty($reference['discriminatorMap'])) {
                    foreach ($reference['discriminatorMap'] as $discriminatorClass) {
                        $this->configClasses[$discriminatorClass]['onDelete'][] = $onDelete;
                    }
                } else {
                    foreach ($this->configClasses as &$configClass) {
                        $configClass['onDelete'][] = $onDelete;
                    }
                }
            }
        }
    }

    private function globalHasGroupsProcess(): void
    {
        do {
            $continue = false;
            foreach ($this->configClasses as $configClass) {
                if (isset($configClass['_has_groups'])) {
                    continue;
                }

                $hasGroups = false;
                if ($configClass['referencesMany'] || $configClass['embeddedsMany']) {
                    $hasGroups = true;
                }
                foreach (array_merge($configClass['embeddedsOne'], $configClass['embeddedsMany']) as $embedded) {
                    if (!isset($this->configClasses[$embedded['class']]['_has_groups'])) {
                        $continue = true;
                        continue 2;
                    }
                    if ($this->configClasses[$embedded['class']]['_has_groups']) {
                        $hasGroups = true;
                    }
                }
                $configClass['_has_groups'] = $hasGroups;
            }
        } while($continue);
    }

    private function globalIndexesProcess(): void
    {
        do {
            $continue = false;
            foreach ($this->configClasses as $configClass) {
                if (isset($configClass['_indexes'])) {
                    continue;
                }

                $indexes = $configClass['indexes'];
                foreach (array_merge($configClass['embeddedsOne'], $configClass['embeddedsMany']) as $name => $embedded) {
                    if (!isset($this->configClasses[$embedded['class']]['_indexes'])) {
                        $continue = true;
                        continue 2;
                    }
                    $embeddedIndexes = [];
                    foreach ($this->configClasses[$embedded['class']]['_indexes'] as $index) {
                        $newKeys = [];
                        foreach ($index['keys'] as $keyName => $value) {
                            $newKeys[$name.'.'.$keyName] = $value;
                        }
                        $index['keys'] = $newKeys;
                        $embeddedIndexes[] = $index;
                    }
                    $indexes = array_merge($indexes, $embeddedIndexes);
                }
                $configClass['_indexes'] = $indexes;
            }
        } while ($continue);
    }

    /*
     * postGlobal
     */
    private function globalMetadataProcess(): void
    {
        $output = new Output($this->getOption('metadata_factory_output'), true);
        $definition = new Definition($this->getOption('metadata_factory_class'), $output);
        $definition->setParentClass('\Mandango\MetadataFactory');
        $this->definitions['metadata_factory'] = $definition;

        $output = new Output($this->getOption('metadata_factory_output'), true);
        $definition = new Definition($this->getOption('metadata_factory_class').'Info', $output);
        $this->definitions['metadata_factory_info'] = $definition;

        $classes = [];
        foreach ($this->configClasses as $class => $configClass) {
            $classes[$class] = $configClass['isEmbedded'];

            $info = [];
            // general
            $info['isEmbedded'] = $configClass['isEmbedded'];
            if (!$info['isEmbedded']) {
                $info['mandango'] = $configClass['mandango'];
                $info['connection'] = $configClass['connection'];
                $info['collection'] = $configClass['collection'];
            }
            // inheritable
            $info['inheritable'] = $configClass['inheritable'];
            // inheritance
            $info['inheritance'] = $configClass['inheritance'];
            // fields
            $info['fields'] = $configClass['fields'];
            // references
            $info['_has_references'] = $configClass['_has_references'];
            $info['referencesOne'] = $configClass['referencesOne'];
            $info['referencesMany'] = $configClass['referencesMany'];
            // embeddeds
            $info['embeddedsOne'] = $configClass['embeddedsOne'];
            $info['embeddedsMany'] = $configClass['embeddedsMany'];
            // relations
            if (!$info['isEmbedded']) {
                $info['relationsOne'] = $configClass['relationsOne'];
                $info['relationsManyOne'] = $configClass['relationsManyOne'];
                $info['relationsManyMany'] = $configClass['relationsManyMany'];
                $info['relationsManyThrough'] = $configClass['relationsManyThrough'];
            }
            // indexes
            $info['indexes'] = $configClass['indexes'];
            $info['_indexes'] = $configClass['_indexes'];

            $info = Dumper::exportArray($info, 12);

            $method = new Method('public', 'get'.str_replace('\\', '', $class).'Class', '', <<<EOF
        return $info;
EOF
            );
            $this->definitions['metadata_factory_info']->addMethod($method);
        }

        $property = new Property('protected', 'classes', $classes);
        $this->definitions['metadata_factory']->addProperty($property);
    }

    protected function configureTwig(Twig_Environment $twig): void
    {
        $twig->addExtension(new MandangoTwig());
    }

    private function parseAndCheckAssociationClass(&$association, $name): void
    {
        if (!is_array($association)) {
            throw new RuntimeException(sprintf('The association "%s" of the class "%s" is not an array or string.', $name, $this->class));
        }

        if (!empty($association['class'])) {
            if (!is_string($association['class'])) {
                throw new RuntimeException(sprintf('The class of the association "%s" of the class "%s" is not an string.', $name, $this->class));
            }
        } elseif (!empty($association['polymorphic'])) {
            if (empty($association['discriminatorField'])) {
                $association['discriminatorField'] = '_mandangoDocumentClass';
            }
            if (empty($association['discriminatorMap'])) {
                $association['discriminatorMap'] = false;
            }
        } else {
            throw new RuntimeException(sprintf('The association "%s" of the class "%s" does not have class and it is not polymorphic.', $name, $this->class));
        }
    }

    private function mapArrayKeyWithDefault($array, $key, array $mapCallback, $default)
    {
        if (array_key_exists($key, $array)) {
            return call_user_func($mapCallback, $array[$key]);
        }

        return $default;
    }

    private function mapToBoolean($value): bool
    {
        if ($this->isBooleanTrueValue($value)) {
            return true;
        }

        if ($this->isBooleanFalseValue($value)) {
            return false;
        }

        throw new InvalidArgumentException('The value is not a boolean value.');
    }

    private function isBooleanTrueValue($value): bool
    {
        return in_array($value, array(true, 1, '1'), true);
    }

    private function isBooleanFalseValue($value): bool
    {
        return in_array($value, array(false, 0, '0'), true);
    }
}
