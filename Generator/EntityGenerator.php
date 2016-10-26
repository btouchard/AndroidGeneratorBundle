<?php
/**
 * Created by Benjamin Touchard @ 2016
 * Date: 19/10/16
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Table;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Entity;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\File;
use Kolapsis\Bundle\AndroidGeneratorBundle\Parser\BundleParser;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * EntityGenerator
 * Core class to generate Entities parts (Entity, SyncService and Sync Adaptor)
 * Based on skeleton files on resources.
 */
final class EntityGenerator extends Generator {

    private static $DIRECTORY_ID = 100;

    private $javaPath;
    private $parser;
    private $providers;

    public function __construct(\Twig_Environment $twig, OutputInterface $output, $packageName, $path) {
        parent::__construct($twig, $output, $packageName, $path);
        $this->javaPath = $path . '/app/src/main/java/' . str_replace('.', '/', $this->packageName);
    }

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

    private function generateEntity($provider, $entity, $anonymous) {
        $entityName = $this->parser->getEntityName($entity->getName());
        $mappings = $this->getAssociationMappings($entity);
        $this->output->write('Generate: Entity ' . $entityName);
        $target = $this->javaPath . '/entity/' . $entityName . '.java';
        $withData = !empty($entity->getLifecycleCallbacks('postPersist'));
        $dataPropertyName = $withData ? $this->getDataPropertyName($entity) : null;

        $properties = [];
        foreach ($mappings as $mapping) {
            foreach ($mapping['joinColumns'] as $column => $targetColumn) {
                $type = $this->getFieldEntityType($mapping['targetEntity'], $targetColumn);
                $properties[] = [ 'type' => $this->typeToJava($type), 'name' => $column, 'targetEntity' => $this->parser->getEntityName($mapping['targetEntity']) ];
            }
        }
        foreach ($entity->getFieldNames() as $name) {
            if (in_array($name, ['id', 'sourceId', 'account', 'data', $dataPropertyName])) continue;
            $field = $entity->getFieldMapping($name);
            $properties[] = [ 'type' => $this->typeToJava($field['type']), 'name' => $field['fieldName'] ];
        }

        $params = [
            'package' => $this->packageName,
            'entityName' => $entityName,
            'anonymousAccess' => $anonymous,
            'withData' => $withData,
            'providerName' => $provider,
            'directoryId' => self::$DIRECTORY_ID,
            'properties' => $properties,
            'dataPropertyName' => $dataPropertyName,
        ];
        self::$DIRECTORY_ID += 2;

        $this->renderFile('EntityTemplate.java.twig', $target, $params);
        $this->output->writeln(' -> <info>OK</info>');
    }

    private function typeToJava($type) {
        switch ($type) {
            case 'boolean'; return 'bool';
            case 'integer'; return 'int';
            case 'long'; return 'long';
            case 'text'; return 'String';
            default: return ucfirst($type);
        }
    }

    private function getAssociationMappings($entity) {
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

    private function getDataPropertyName($meta) {
        $reflectionClass = new \ReflectionClass($meta->getName());
        $reader = new AnnotationReader();
        foreach ($reflectionClass->getProperties() as $property) {
            $annotations = $reader->getPropertyAnnotations($property);
            foreach ($annotations as $annotation) {
                if ($annotation instanceof File)
                    return $property->getName();
            }
        }
        return null;
    }

    private function getEntityByName($name) {
        foreach ($this->providers as $provider => $metaData)
            foreach ($metaData['entities'] as $entity)
                if ($entity->getName() == $name)
                    return $entity;
        return null;
    }

    private function getFieldEntityType($entityName, $field) {
        $entity = $this->getEntityByName($entityName);
        $type = $entity->getFieldMapping($field);
        if ($type['id']) $type['type'] = 'long';
        return $type['type'];
    }

}