<?php

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;

abstract class Generator {

    protected $twig;
    protected $output;
    protected $packageName;
    protected $path;

    public function __construct(\Twig_Environment $twig, OutputInterface $output, $packageName, $path) {
        $this->twig = $twig;
        $this->output = $output;
        $this->packageName = $packageName;
        $this->path = $path;
    }

    protected function render($template, $parameters)
    {
       return $this->twig->render($template, $parameters);
    }

    protected function renderFile($template, $target, $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $this->render($template, $parameters));
    }
}
