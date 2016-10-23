<?php
/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 19/10/16
 * Time: 11:35
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Common\Annotations\AnnotationReader;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\AndroidAnnotation;
use Symfony\Component\Console\Output\OutputInterface;

class EntityGenerator extends Generator {

    private static $DIRECTORY_ID = 100;

    private $output;
    private $metadata, $javaPath;
    private $packageName, $providers = [];

    public function __construct(\Twig_Environment $twig, OutputInterface $output, $packageName, $javaPath) {
        parent::__construct($twig);
        $this->output = $output;
        $this->packageName = $packageName;
        $this->javaPath = $javaPath;
    }

    public function getProviders() {
        return $this->providers;
    }

    public function prepare(ClassMetadataCollection $metadata) {
        $this->metadata = $metadata;
        $this->providers = $this->prepareEntities();
    }

    public function generate() {
        foreach ($this->providers as $provider => $entities) {
            $entityNames = $this->getEntityNames($entities);
            $this->generateProvider($provider, $entityNames);
            $this->generateSync($provider, $entityNames);
            foreach ($entities as $entity) {
                $this->generateEntity($provider, $entity);
            }
        }
    }

    private function prepareEntities() {
        $providers = [];
        foreach ($this->metadata->getMetadata() as $meta) {
            if (!$this->isIgnoredEntity($meta)) {
                $name = $this->getProviderName($meta);
                $providers[$name][] = $meta;
            }
        }
        return $providers;
    }

    private function isIgnoredEntity($meta) {
        $reflectionClass = new \ReflectionClass($meta->getName());
        $reader = new AnnotationReader();
        return ($annotation = $reader->getClassAnnotation($reflectionClass, AndroidAnnotation::class)) ? $annotation->ignored : false;
    }
    private function isAnonymousAccess($meta) {
        $reflectionClass = new \ReflectionClass($meta->getName());
        $reader = new AnnotationReader();
        return ($annotation = $reader->getClassAnnotation($reflectionClass, AndroidAnnotation::class)) ? $annotation->anonymousAccess : false;
    }
    private function getProviderName($meta) {
        $reflectionClass = new \ReflectionClass($meta->getName());
        $reader = new AnnotationReader();
        return ($annotation = $reader->getClassAnnotation($reflectionClass, AndroidAnnotation::class)) ? $annotation->provider.'s' : 'Entities';
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

    private function getEntityNames($entities) {
        $names = [];
        foreach ($entities as $entity)
            $names[] = $this->getEntityName($entity->getName());
        return $names;
    }

    private function generateSync($provider, $entities) {
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
            'entities' => $entities,
        ]);
        $this->output->writeln(' -> <info>OK</info>');
    }

    private function generateEntity($provider, $entity) {
        $entityName = $this->getEntityName($entity->getName());
        $anonymousAccess = $this->isAnonymousAccess($entity);
        $this->output->write('Generate: Entity ' . $entityName);
        $target = $this->javaPath . '/entity/' . $entityName . '.java';
        $withData = !empty($entity->getLifecycleCallbacks('postPersist'));

        $properties = [];
        foreach ($entity->getFieldNames() as $name) {
            if (in_array($name, ['id', 'sourceId', 'account', 'data'])) continue;
            $field = $entity->getFieldMapping($name);
            $properties[] = [ 'type' => $this->typeToJava($field['type']), 'name' => $field['fieldName'] ];
        }

        $params = [
            'package' => $this->packageName,
            'entityName' => $entityName,
            'anonymousAccess' => $anonymousAccess,
            'withData' => $withData,
            'providerName' => $provider,
            'directoryId' => self::$DIRECTORY_ID,
            'properties' => $properties,
        ];
        self::$DIRECTORY_ID += 2;

        $this->renderFile('EntityTemplate.java.twig', $target, $params);
        $this->output->writeln(' -> <info>OK</info>');
    }

    private function typeToJava($type) {
        switch ($type) {
            case 'boolean'; return 'bool';
            case 'integer'; return 'int';
            case 'text'; return 'String';
            default: return ucfirst($type);
        }
    }

    public function extractProviderNames() {
        return array_keys($this->providers);
    }

    public function extractEntityNames() {
        $names = [];
        foreach ($this->providers as $entities)
            foreach ($entities as $entity)
                $names[] = $this->getEntityName($entity->getName());
        return $names;
    }

    public function extractUserClass() {

    }

    private function getEntityName($entity) {
        return preg_replace('/(\w+\\\\)*/', '', $entity);
    }

}