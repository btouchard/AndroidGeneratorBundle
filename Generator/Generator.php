<?php
/**
 * Abstract Class Generator
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;


use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract Class Generator
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Parser
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
abstract class Generator {

    /**
     * Twig environment
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * Command Line Output
     * @var OutputInterface
     */
    protected $output;

    /**
     * Android application package name
     * @var string
     */
    protected $packageName;

    /**
     * Android application target path
     * @var string
     */
    protected $path;

    /**
     * Base Generator constructor.
     * @param \Twig_Environment $twig
     * @param OutputInterface $output
     * @param $packageName
     * @param $path
     */
    public function __construct(\Twig_Environment $twig, OutputInterface $output, $packageName, $path) {
        $this->twig = $twig;
        $this->output = $output;
        $this->packageName = $packageName;
        $this->path = $path;
    }

    /**
     * Make twig render
     * @param $template
     * @param $parameters
     * @return string
     */
    protected function render($template, $parameters) {
       return $this->twig->render($template, $parameters);
    }

    /**
     * Render in file
     * @param $template
     * @param $target
     * @param $parameters
     */
    protected function renderFile($template, $target, $parameters) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $this->render($template, $parameters));
    }
}
