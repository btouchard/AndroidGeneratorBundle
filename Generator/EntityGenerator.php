<?php
/**
 * Class EntityGenerator
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;


use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Table;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Api;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Entity;
use Kolapsis\Bundle\AndroidGeneratorBundle\Exception\GeneratorException;
use Kolapsis\Bundle\AndroidGeneratorBundle\Parser\BundleParser;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Core class to generate Entities parts (Entity, SyncService and Sync Adaptor)
 * Based on skeleton files on resources and definitions from parser.
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Generator
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
final class EntityGenerator extends Generator {

    /**
     * Counter for android ContentProvider ID
     * @var int
     */
    private static $DIRECTORY_ID = 100;

    /**
     * App container
     * @var ContainerInterface
     */
    private $container;

    /**
     * Java destination path
     * @var string
     */
    private $javaPath;

    /**
     * BundleParser reference
     * @var BundleParser
     */
    private $parser;

    /**
     * BundleParser providers definition reference
     * @var array
     */
    private $providers;

    /**
     * EntityGenerator constructor.
     * @param \Twig_Environment $twig
     * @param OutputInterface $output
     * @param $packageName
     * @param $path
     */
    public function __construct(ContainerInterface $container, \Twig_Environment $twig, OutputInterface $output, $packageName, $path) {
        parent::__construct($twig, $output, $packageName, $path);
        $this->container = $container;
        $this->javaPath = $path . '/app/src/main/java/' . str_replace('.', '/', $this->packageName);
    }

    /**
     * Generate Android Provider, Sync and Entities for all provider definition
     * @param BundleParser $parser
     */
    public function generate(BundleParser $parser) {
        $this->parser = $parser;
        $this->providers = $parser->providers();
        foreach ($this->providers as $provider => $metaData) {
            if (!empty($provider)) {
                $this->generateProvider($provider, $metaData['entities']);
                $this->generateSync($provider, $metaData['entities'], $metaData['anonymous']);
            }
            foreach ($metaData['entities'] as $entity)
                $this->generateEntity($provider, $entity, $metaData['anonymous']);
        }
    }

    /**
     * Generate Android ContentProvider's
     * @param string $provider
     * @param array $entities
     */
    private function generateProvider($provider, $entities) {
        $this->output->write('Generate: ' . $provider . 'Provider');
        $target = $this->javaPath . '/providers/' . $provider . 'Provider.java';
        // echo '-> target:' . $target . PHP_EOL;
        $this->renderFile('ProviderTemplate.java.twig', $target, [
            'package' => $this->packageName,
            'provider' => $provider,
            'entities' => $entities,
        ]);
        $this->output->writeln(' -> <info>OK</info>');
    }

    /**
     * Generate Android SyncService and SyncAdapter
     * @param string $provider
     * @param array $entities
     * @param bool $anonymous
     */
    private function generateSync($provider, $entities, $anonymous) {
        $this->output->write('Generate: ' . $provider . 'SyncService');
        $target = $this->javaPath . '/sync/' . $provider . 'SyncService.java';
        // echo '-> target:' . $target . PHP_EOL;
        $this->renderFile('SyncServiceTemplate.java.twig', $target, [
            'package' => $this->packageName,
            'provider' => $provider,
        ]);
        $this->output->writeln(' -> OK');
        $this->output->write('Generate: ' . $provider . 'SyncAdapter');
        $target = $this->javaPath . '/sync/' . $provider . 'SyncAdapter.java';
        // echo '-> target:' . $target . PHP_EOL;
        $this->renderFile('SyncAdapterTemplate.java.twig', $target, [
            'package' => $this->packageName,
            'provider' => $provider,
            'anonymous' => $anonymous,
            'entities' => $entities,
        ]);
        $this->output->writeln(' -> <info>OK</info>');
    }

    /**
     * Generate Android Entity
     * @param string $provider
     * @param ClassMetadata $entity
     * @param bool $anonymous
     */
    private function generateEntity($provider, ClassMetadata $entity, $anonymous) {
        $entityName = $this->parser->getEntityName($entity->getName());
        $this->output->write('Generate: Entity ' . $entityName);
        $tableName = $this->getTableName($entity);
        $apiPath = $this->getApiPath($entity);
        $mappings = $this->getAssociationMappings($entity);
        $target = $this->javaPath . '/entity/' . $entityName . '.java';
        $filePropertyName = $this->getFilePropertyName($entity);
        $withData = !empty($filePropertyName); //!empty($entity->getLifecycleCallbacks('postPersist'));
        $properties = [];
        foreach ($mappings as $mapping) {
            foreach ($mapping['joinColumns'] as $column => $targetColumn) {
                $type = $this->getFieldEntityType($mapping['targetEntity'], $targetColumn);
                $properties[] = [ 'type' => $this->typeToJava($type), 'name' => $column, 'targetEntity' => $this->parser->getEntityName($mapping['targetEntity']) ];
            }
        }
        foreach ($entity->getFieldNames() as $name) {
            if (in_array($name, ['id', 'sourceId', 'account', 'data', $filePropertyName])) continue;
            $field = $entity->getFieldMapping($name);
            $properties[] = [ 'type' => $this->typeToJava($field['type']), 'name' => $field['fieldName'] ];
        }

        $params = [
            'package' => $this->packageName,
            'entityName' => $entityName,
            'tableName' => $tableName,
            'apiPath' => $apiPath,
            'anonymousAccess' => $anonymous,
            'withData' => $withData,
            'providerName' => $provider,
            'directoryId' => self::$DIRECTORY_ID,
            'properties' => $properties,
            'filePropertyName' => $filePropertyName,
        ];
        self::$DIRECTORY_ID += 2;

        $this->renderFile('EntityTemplate.java.twig', $target, $params);
        $this->output->writeln(' -> <info>OK</info>');
    }

    /**
     * Convert PHP/ORM type to Java type
     * @param string $type
     * @return string
     */
    private function typeToJava($type) {
        switch ($type) {
            case 'boolean': return 'bool';
            case 'integer': return 'int';
            case 'float':
            case 'double':
                return 'double';
            case 'long': return 'long';
            case 'text': return 'String';
            default: return ucfirst($type);
        }
    }

    /**
     * Return association mapping for Entity
     * @param ClassMetadata $entity
     * @return array
     */
    private function getAssociationMappings(ClassMetadata $entity) {
        $result = [];
        $mappings = $entity->getAssociationMappings();
        foreach ($mappings as $assoc) {
            if ($assoc['type'] == 2) {
                $result[] = [
                    'targetEntity' => $assoc['targetEntity'],
                    'joinColumns' => $assoc['sourceToTargetKeyColumns'],
                ];
            }
        }
        return $result;
    }

    /**
     * Return SQLite table name for Entity
     * @param ClassMetadata $entity
     * @return string
     */
    private function getTableName(ClassMetadata $entity) {
        $reflectionClass = new \ReflectionClass($entity->getName());
        $reader = new AnnotationReader();
        $annotations = $reader->getClassAnnotation($reflectionClass, Entity::class);
        if ($annotations != null) {
            $name = $annotations->tableName;
            if (empty($name)) {
                $table = $reader->getClassAnnotation($reflectionClass, Table::class);
                if ($table != null && !empty($table->name)) $name = $table->name;
            }
        }
        if (empty($name))
            $name = strtolower(preg_replace('/\B([A-Z])/', '_$1', $entity->getName()));
        return $name;
    }

    /**
     * Return api path for Entity
     * @param ClassMetadata $entity
     * @return string
     */
    private function getApiPath(ClassMetadata $entity) {
        $reflectionClass = new \ReflectionClass($entity->getName());
        $reader = new AnnotationReader();
        $annotations = $reader->getClassAnnotation($reflectionClass, Api::class);
        if (!empty($annotations->path))
            return $annotations->path;
        $inflect = $this->container->get('kolapsis.string.utils');
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $inflect->pluralize($entity->getName())));
    }

    /**
     * Return Android Entity data property name (for download/upload)
     * @param ClassMetadata $entity
     * @return null|string
     */
    private function getFilePropertyName(ClassMetadata $entity) {
        $reflectionClass = new \ReflectionClass($entity->getName());
        $reader = new AnnotationReader();
        $annotations = $reader->getClassAnnotation($reflectionClass, Entity::class);
        if (!empty($annotations->file))
            if (in_array($annotations->file, $entity->getFieldNames()))
                return $annotations->file;
            else
                throw new GeneratorException("Field " . $annotations->file . " must be defined as attribute in " . $entity->getName());
        return null;
    }

    /**
     * Find and return entity in provider definition
     * @param string $name
     * @return null|ClassMetadata
     */
    private function getEntityByName($name) {
        foreach ($this->providers as $provider => $metaData)
            foreach ($metaData['entities'] as $entity)
                if ($entity->getName() == $name)
                    return $entity;
        return null;
    }

    /**
     * Return type of specific field in Entity
     * @param string $entityName
     * @param string $field
     * @return string
     */
    private function getFieldEntityType($entityName, $field) {
        $entity = $this->getEntityByName($entityName);
        $type = $entity->getFieldMapping($field);
        if ($type['id']) $type['type'] = 'long';
        return $type['type'];
    }

}