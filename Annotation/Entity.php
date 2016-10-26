<?php
/**
 * Created by Benjamin Touchard @ 2016
 * Date: 19/10/16
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Annotation;

/**
 * Entity
 * Annotation for Android Entity definition
 *
 * @Annotation
 * @Target("CLASS")
 */
final class Entity {

    /**
     * Android Entity name
     *
     * @var string
     */
    public $entityName;

    /**
     * Android Provider name of entity (default = 'Default')
     *
     * @var string
     */
    public $providerName = 'Default';

    /**
     * Authorize anonymous Entity access on API (no authentication needed)
     *
     * @var bool
     */
    public $anonymousAccess;

    /**
     * Android SQLite table name (default = Doctrine\ORM table name)
     *
     * @var string
     */
    public $tableName;

    /**
     * Use to ignore class in Android Project
     *
     * @var boolean
     */
    public $ignoredClass = false;

    public function __construct($options) {
        if (isset($options['value'])) {
            $options['entityName'] = $options['value'];
            unset($options['value']);
        }
        foreach ($options as $key => $value) {
            if (property_exists($this, $key))
                $this->$key = $value;
        }
    }

}