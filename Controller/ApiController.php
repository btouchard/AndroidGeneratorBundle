<?php
/**
 * Class ApiController
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataCollection;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\View;
use Kolapsis\Bundle\AndroidGeneratorBundle\Annotation\Api;
use Kolapsis\Bundle\AndroidGeneratorBundle\Form\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Expose Bundle entity with RESTFull Api
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Generator
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
class ApiController extends Controller implements ContainerAwareInterface {

    /**
     * Application kernel
     * @var KernelInterface
     */
    private $kernel;

    /**
     * Web root (use for file access)
     * @var string
     */
    private $webRoot;

    /**
     * Doctrine\ORM Metadata
     * @var DisconnectedMetadataFactory
     */
    private $manager;


    /**
     * {@inheritdoc}
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null) {
        parent::setContainer($container);
        $this->kernel = $container->get('kernel');
        $this->webRoot = realpath($this->kernel->getRootDir() . '/../web');
        $this->manager = new DisconnectedMetadataFactory($container->get('doctrine'));
    }

    /**
     * Return all users
     * @Route("/users", methods={"GET"}, name="api_users")
     * @View(serializerGroups={"Default"})
     * @return mixed
     */
    public function getUsersAction(){
        return $this->getDoctrine()->getRepository('AppBundle:User')->findAll();
    }

    /**
     * Return current user if authenticated (use by Android App to check token)
     * @Route("/me", methods={"GET"}, name="api_me")
     * @View(serializerGroups={"Default","Me", "Details"})
     * @return mixed
     */
    public function getMeAction(){
        return $this->getUser();
    }

    /*******************************************************
     *** Entity ********************************************
     ******************************************************/

    /**
     * Return json entity collection
     * @Route("/{entityName}", requirements={"entityName"="[\w_]+"}, methods={"GET"}, name="api_get_entities")
     //* @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @return array
     */
    public function getAllAction(Request $request, $entityName) {
        $entity = $this->getEntity($request, $entityName);
        $this->checkAllowedMethod($request->getMethod(), $entity);
        return $this->getDoctrine()->getRepository($entity->getName())->findAll();
    }

    /**
     * Return json entity
     * @Route("/{entityName}/{id}", requirements={"entityName"="[\w_]+", "id"="\d+"}, methods={"GET"}, name="api_get_entity")
     //* @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @param $id
     * @return object
     */
    public function getOneAction(Request $request, $entityName, $id) {
        $entity = $this->getEntity($request, $entityName);
        $this->checkAllowedMethod($request->getMethod(), $entity);
        $value = $this->getDoctrine()->getRepository($entity->getName())->find($id);
        if (!is_object($value)) throw $this->createResourceNotFoundException($entityName);
        return $value;
    }

    /**
     * Insert entity
     * @Route("/{entityName}", requirements={"entityName"="[\w_]+"}, methods={"POST"}, name="api_post_entity")
     * @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @return array
     */
    public function postAction(Request $request, $entityName) {
        $entity = $this->getEntity($request, $entityName);
        $this->checkAllowedMethod($request->getMethod(), $entity);
        $factory = Forms::createFormFactory();
        $form = $factory->createBuilder(EntityType::class, null, ['entity' => $entity, 'data_class' => $entity->getName()])->getForm();
        $form->submit($request->request->all(), false);
        $insert = $form->getData();
        $errors = $this->get('validator')->validate($insert);
        if ($errors->count() > 0)
            throw new BadRequestHttpException($error = $errors->get(0)->getMessage());
        $em = $this->getDoctrine()->getManager();
        $em->persist($insert);
        $em->flush();
        return ['id' => $insert->getId()];
    }

    /**
     * Update entity
     * @Route("/{entityName}/{id}", requirements={"entityName"="[\w_]+", "id"="\d+"}, methods={"PUT"}, name="api_put_entity")
     * @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @param $id
     * @return array
     */
    public function putAction(Request $request, $entityName, $id) {
        $entity = $this->getEntity($request, $entityName);
        $this->checkAllowedMethod($request->getMethod(), $entity);
        $obj = $this->getDoctrine()->getRepository($entity->getName())->find($id);
        if (!is_object($obj)) throw $this->createResourceNotFoundException($entityName);
        $factory = Forms::createFormFactory();
        $form = $factory->createBuilder(EntityType::class, $obj, ['entity' => $entity, 'data_class' => $entity->getName()])->getForm();
        $form->submit($request->request->all(), false);
        $update = $form->getData();
        $errors = $this->get('validator')->validate($update);
        if ($errors->count() > 0)
            throw new BadRequestHttpException($error = $errors->get(0)->getMessage());
        $em = $this->getDoctrine()->getManager();
        $em->persist($update);
        $em->flush();
        return ['id' => $update->getId()];
    }

    /**
     * Delete entity
     * @Route("/{entityName}/{id}", requirements={"entityName"="[\w_]+", "id"="\d+"}, methods={"DELETE"}, name="api_delete_entity")
     * @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @param $id
     * @return array
     */
    public function deleteAction(Request $request, $entityName, $id) {
        $entity = $this->getEntity($request, $entityName);
        $this->checkAllowedMethod($request->getMethod(), $entity);
        $obj = $this->getDoctrine()->getRepository($entity->getName())->find($id);
        if (!is_object($obj)) throw $this->createResourceNotFoundException($entityName);
        $em = $this->getDoctrine()->getManager();
        $em->remove($obj);
        $em->flush();
        return '';
    }

    /**
     * Check, Read and Save entity file
     * @Route("/{entityName}/{id}/{field}", requirements={"entityName"="[\w_]+", "id"="\d+", "path"="[\w_]+"}, methods={"HEAD","GET","POST"}, name="api_file")
     * @View(serializerGroups={"Default"})
     * @param Request $request
     * @param $entityName
     * @param $id
     * @param $field
     * @return object
     */
    public function fileAction(Request $request, $entityName, $id, $field) {
        $entity = $this->getEntity($request, $entityName);
        if ($request->getMethod() != 'HEAD')
            $this->checkAllowedMethod($request->getMethod(), $entity);
        $value = $this->getDoctrine()->getRepository($entity->getName())->find($id);
        if (!is_object($value)) throw $this->createResourceNotFoundException($entityName);
        $path = realpath($value->getAbsolutePath());
        switch ($request->getMethod()) {
            case 'HEAD':
                return new Response('', 200, ['Content-Length' => is_file($path) ? filesize($path) : 0]);
            case 'POST':
                $file = $request->files->get($field);
                if ($file != null && $file->getError() == 0) {
                    $value->setFile($file);
                    $em = $this->get('doctrine.orm.entity_manager');
                    $em->persist($value);
                    $em->flush();
                    return $value;
                }
                throw new BadRequestHttpException("Exceeded file size");
            default:
                if (!is_file($path)) throw $this->createNotFoundException();
                return new BinaryFileResponse($path);
        }
    }


    /*******************************************************
     *** PRIVATE *******************************************
     ******************************************************/

    /**
     * @param $entityName
     * @return ResourceNotFoundException
     */
    private function createResourceNotFoundException($entityName) {
        return new ResourceNotFoundException("Resource not found: $entityName");
    }

    /**
     * Extract providers and entities from metadata
     *
     * @param ClassMetadataCollection $metadata
     * @return array
     */
    private function parseBundle(ClassMetadataCollection $metadata) {
        $routes = [];
        foreach ($metadata->getMetadata() as $meta) {
            $reflectionClass = new \ReflectionClass($meta->getName());
            $reader = new AnnotationReader();
            $annotation = $reader->getClassAnnotation($reflectionClass, Api::class);
            if ($annotation && $annotation->path)
                $routes[$annotation->path] = $meta;
        }
        return $routes;
    }

    /**
     * Return entity for request path
     * @param Request $request
     * @param $entityName
     * @return ClassMetadata
     */
    private function getEntity(Request $request, $entityName) {
        $controller = $request->attributes->get('_controller');
        $bundleName = preg_replace("/(\\w+).*/", "$1", $controller);
        $bundle = $this->kernel->getBundle($bundleName);
        $metadata = $this->manager->getBundleMetadata($bundle);
        $routes = $this->parseBundle($metadata);
        if (isset($routes[$entityName]))
            return $routes[$entityName];
        throw $this->createResourceNotFoundException($entityName);
    }

    /**
     * Throw Not allowed exception if mathod is not allowed in annotations entity
     * @param String $method
     * @param ClassMetadata $entity
     */
    private function checkAllowedMethod($method, ClassMetadata $entity) {
        $reflectionClass = new \ReflectionClass($entity->getName());
        $reader = new AnnotationReader();
        $annotation = $reader->getClassAnnotation($reflectionClass, Api::class);
        if ($annotation && $annotation->methods) {
            if (!in_array($method, $annotation->methods))
                throw new MethodNotAllowedHttpException($annotation->methods);
        }
    }

}
