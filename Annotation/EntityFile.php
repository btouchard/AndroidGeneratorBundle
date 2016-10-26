<?php
/**
 * Class EntityFile Annotation
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Annotation;

/**
 * Annotation for Android Entity file upload definition
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Annotation
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class EntityFile {

    /**
     * Entity file field name
     *
     * @var string
     */
    public $field;

    /**
     * Annotation constructor.
     * @param $options
     */
    public function __construct($options) {
        if (isset($options['value'])) {
            $options['field'] = $options['value'];
            unset($options['value']);
        }
        foreach ($options as $key => $value) {
            if (property_exists($this, $key))
                $this->$key = $value;
        }
    }

}