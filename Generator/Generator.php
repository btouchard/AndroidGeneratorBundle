<?php

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

class Generator {

    private $twig;

    public function __construct(\Twig_Environment $twig) {
        $this->twig = $twig;
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
        return file_put_contents($target, $this->render($template, $parameters));
    }
}
