<?php
/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 19/10/16
 * Time: 11:35
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;


class FileGenerator extends Generator {

    private $packageName;

    public function __construct(\Twig_Environment $twig, $packageName) {
        parent::__construct($twig);
        $this->packageName = $packageName;
    }

    public function generate($path, $target, $params=[]) {
        //$target = $javaPath . '/' . $path . '.java';
        $params['package'] = $this->packageName;
        $this->renderFile($path.'.twig', $target, $params);
    }

    public function setupConf($path, $pattern, $replacement) {
        $content = file_get_contents($path);
        $content = preg_replace($pattern, $replacement, $content);
        file_put_contents($path, $content);
    }

}