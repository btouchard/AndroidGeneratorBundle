<?php
/**
 * Class BundleParser
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Parser;


use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Schema\Table;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Entity;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * This class provide a bundle parsing for android definitions
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Parser
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
class BundleParser {

    /**
     * Command Line Output
     * @var OutputInterface
     */
    private $output;

    /**
     * Symfony container
     * @var ContainerInterface
     */
    private $container;

    /**
     * Meta factory
     * @var DisconnectedMetadataFactory
     */
    private $manager;

    /**
     * Android providers definition
     * @var array
     */
    private $providers = [];

    /**
     * Android account user columns
     * @var array
     */
    private $userColumns = [];

    /**
     * Construct Bundle Parser
     *
     * @param OutputInterface $output
     * @param ContainerInterface $container
     */
    public function __construct(OutputInterface $output, ContainerInterface $container) {
        $this->output = $output;
        $this->container = $container;
        $this->manager = new DisconnectedMetadataFactory($this->container->get('doctrine'));
    }

    /**
     * Parse entities in bundle and extract Android provider and entity hierarchy
     *
     * @param BundleInterface $bundle
     */
    public function parse(BundleInterface $bundle) {
        $metadata = $this->manager->getBundleMetadata($bundle);
        $this->output->write('Parsing: ' . $metadata->getNamespace());
        $this->parseProviders($metadata);
        $this->parseUserColumns();
        $this->output->writeln(' -> <info>OK</info>');
    }

    /**
     * Extract providers and entities from metadata
     *
     * @param ClassMetadataCollection $metadata
     */
    private function parseProviders(ClassMetadataCollection $metadata) {
        $this->providers = [];
        foreach ($metadata->getMetadata() as $meta) {
            $reflectionClass = new \ReflectionClass($meta->getName());
            $reader = new AnnotationReader();
            $annotation = $reader->getClassAnnotation($reflectionClass, Entity::class);
            if (!empty($annotation) && !$annotation->ignoredClass) {
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

    /**
     * Extract user columns from your FOSUser class
     */
    private function parseUserColumns() {
        $this->userColumns = [];
        $class = $this->container->get('fos_user.user_manager')->getClass();
        if ($class != null) {
            $meta = $this->manager->getClassMetadata($class);
            $this->userColumns = array_diff($meta->getMetaData()[0]->getFieldNames(), ['id']);
        }
    }

    /**
     * Return android providers array definitions
     *
     * @return array
     */
    public function providers() {
        return $this->providers;
    }

    /**
     * Return user columns array
     *
     * @return array
     */
    public function userColumns() {
        return $this->userColumns;
    }

    /**
     * Extract android providers names and return it in array
     *
     * @param bool $anonymousOnly
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
     * Extract android authorities and return it in array
     *
     * @param bool $anonymousOnly
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
     * Extract all android entities names and return it in array
     *
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
     * Get entity name from namespace
     *
     * @param string $entity
     * @return string
     */
    public function getEntityName($entity) {
        return preg_replace('/(\w+\\\\)*/', '', $entity);
    }

}