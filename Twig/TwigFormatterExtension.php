<?php
/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 19/10/16
 * Time: 16:11
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Twig;


class TwigFormatterExtension extends \Twig_Extension {

    protected $env;

    public function getName()
    {
        return 'Twig Formatter Filters';
    }

    public function getFilters()
    {
        return array(
            'ucfirst' => new \Twig_Filter_Method($this, '_ucfirst'),
            'lcfirst' => new \Twig_Filter_Method($this, '_lcfirst'),
            'slug' => new \Twig_Filter_Method($this, '_slug'),
            'vnd' => new \Twig_Filter_Method($this, '_vnd'),
            'sqlName' => new \Twig_Filter_Method($this, '_sqlName'),
            'sqlType' => new \Twig_Filter_Method($this, '_sqlType'),
            'attrName' => new \Twig_Filter_Method($this, '_attrName'),
        );
    }

    public function initRuntime(\Twig_Environment $env)
    {
        $this->env = $env;
    }

    /**
     * Perform ucfirst
     *
     * @param string $value
     * @return string
     */
    public function _ucfirst($value)
    {
        if (!isset($value)) {
            return null;
        }
        else {
            return ucfirst($value);
        }
    }

    /**
     * Perform lcfirst
     *
     * @param string $value
     * @return string
     */
    public function _lcfirst($value)
    {
        if (!isset($value)) {
            return null;
        }
        else {
            return lcfirst($value);
        }
    }

    /**
     * Perform Slug transform
     *
     * @param string $subject
     * @return string
     */
    public function _slug($subject)
    {
        if (!isset($subject)) {
            return null;
        }
        else {
            return strtolower(preg_replace('/\B([A-Z])/', '_$1', $subject));
            //return preg_replace('/[\s_-]+/', $replacement, strtolower($subject));
        }
    }

    /**
     * Perform package to vnd transform
     *
     * @param string $package
     * @return string
     */
    public function _vnd($package)
    {
        if (!isset($package)) {
            return null;
        }
        else {
            return preg_replace('/(com)/', 'vnd', $package);
        }
    }

    /**
     * Perform sql column name transform
     *
     * @param string $name
     * @return string
     */
    public function _sqlName($name)
    {
        if (!isset($name)) {
            return null;
        }
        else {
            return strtolower(preg_replace('/\B([A-Z])/', '_$1', $name));
        }
    }

    /**
     * Perform package to vnd transform
     *
     * @param string $type
     * @return string
     */
    public function _sqlType($type)
    {
        if (!isset($type)) {
            return null;
        }
        else {
            switch ($type) {
                case 'bool': return 'INTEGER';
                case 'int': return 'INTEGER';
                case 'float':
                case 'double':
                    return 'REAL';
                case 'String': return 'TEXT';
            }
            return 'BLOB';
        }
    }

    /**
     * Perform an attribute name conversion
     *
     * @param string $name
     * @param string $separator
     * @return string
     */
    public function _attrName($name, $separator='_')
    {
        if (!isset($name)) {
            return null;
        }
        else {
            return lcfirst(str_replace($separator, '', ucwords($name, $separator)));
        }
    }
}