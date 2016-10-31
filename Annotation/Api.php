<?php
/**
 * Class Entity Annotation
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Annotation;

/**
 * Annotation for Android RESTFull Api definition
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Annotation
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class Api {

    /**
     * Android RESTFull Api path
     *
     * @var string
     */
    public $path;

    /**
     * Android RESTFull Api allowed method(s)
     *
     * @var array
     */
    public $methods = [];

    /**
     * Annotation constructor.
     * @param $options
     */
    public function __construct($options) {
        if (isset($options['value'])) {
            $options['path'] = $options['value'];
            unset($options['value']);
        }
        foreach ($options as $key => $value) {
            if (property_exists($this, $key))
                $this->$key = $value;
        }
    }

}