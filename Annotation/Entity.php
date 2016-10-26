<?php
/**
 * Class Entity Annotation
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Annotation;
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Annotation for Android Entity definition
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Annotation
 * @author Benjamin Touchard <benjamin@kolapsis.com>
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
     * Use to ignore class in Android Project (default = false)
     *
     * @var boolean
     */
    public $ignoredClass = false;

    /**
     * Annotation constructor.
     * @param $options
     */
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