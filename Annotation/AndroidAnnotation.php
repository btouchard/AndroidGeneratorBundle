<?php

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Annotation;

/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 19/10/16
 * Time: 22:40
 *
 * @Annotation
 * @Target("CLASS")
 */
final class AndroidAnnotation {

    /**
     * Parameter provider
     *
     * @var string
     */
    public $provider;

    /**
     * Parameter ignored
     *
     * @var boolean
     */
    public $ignored;
}