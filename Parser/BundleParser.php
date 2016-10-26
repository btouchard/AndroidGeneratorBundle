<?php
/**
 * Created by Benjamin Touchard @ 2016
 * Date: 19/10/16
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Parser;


use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Schema\Table;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Entity;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BundleParser {

    private $output;
    private $container;
    private $manager;
    private $providers = [];
    private $userColumns = [];

    public function __construct(OutputInterface $output, ContainerInterface $container) {
        $this->output = $output;
        $this->container = $container;
        $this->manager = new DisconnectedMetadataFactory($this->container->get('doctrine'));
    }

    public function parse($bundle) {
        $metadata = $this->manager->getBundleMetadata($bundle);
        $this->output->write('Parsing: ' . $metadata->getNamespace());
        $this->parseProviders($metadata);
        $this->parseUserColumns();
        $this->output->writeln(' -> <info>OK</info>');
    }

    private function parseProviders($metadata) {
        $this->providers = [];
        foreach ($metadata->getMetadata() as $meta) {
            $reflectionClass = new \ReflectionClass($meta->getName());
            $reader = new AnnotationReader();
            $annotation = $reader->getClassAnnotation($reflectionClass, Entity::class);
            if (!$annotation->ignoredClass) {
                if (empty($annotation->tableName)) {
                    $table = $reader->getClassAnnotation($reflectionClass, Table::class);
                    if ($table != null && !empty($table->name)) $annotation->tableName = $table->name;
                }
                $providerName = $annotation->providerName;
                $this->providers[$providerName]['anonymous'] = (isset($this->providers[$providerName]['anonymous']) ? $this->providers[$providerName]['anonymous'] : false)
                    || $annotation->anonymousAccess;

                $this->providers[$providerName]['entities'][] = $meta;
            }
        }
    }

    private function parseUserColumns() {
        $this->userColumns = [];
        $class = $this->container->get('fos_user.user_manager')->getClass();
        if ($class != null) {
            $meta = $this->manager->getClassMetadata($class);
            $this->userColumns = array_diff($meta->getMetaData()[0]->getFieldNames(), ['id']);
        }
        return $this->userColumns;
    }

    /**
     * @return array
     */
    public function providers() {
        return $this->providers;
    }

    /**
     * @return array
     */
    public function userColumns() {
        return $this->userColumns;
    }

    /**
     * @param $anonymousOnly
     * @return array
     */
    public function providerNames($anonymousOnly=false) {
        $names = [];
        foreach ($this->providers as $provider => $metaData)
            if (!$anonymousOnly || $metaData['anonymous'])
                $names[] = $provider;
        return $names;
    }

    /**
     * @param $anonymousOnly
     * @return array
     */
    public function authorities($anonymousOnly=false) {
        $authorities = [];
        foreach ($this->providers as $provider => $metaData) {
            if (!$anonymousOnly || $metaData['anonymous'])
                $authorities[] = $provider . 'Provider.AUTHORITY';
        }
        return $authorities;
    }

    /**
     * @return array
     */
    public function entityNames() {
        $names = [];
        foreach ($this->providers as $provider => $metaData)
            foreach ($metaData['entities'] as $entity)
                $names[] = $this->getEntityName($entity->getName());
        return $names;
    }

    /**
     * @param $entity
     * @return string
     */
    public function getEntityName($entity) {
        return preg_replace('/(\w+\\\\)*/', '', $entity);
    }

}