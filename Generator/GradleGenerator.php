<?php
/**
 * Class GradleGenerator
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

/**
 * Core class to configure Android application.
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Generator
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
final class GradleGenerator extends Generator {

    /**
     * Generate Gradle configuration for Android App
     *
     * @param $gradleVersion
     * @param $sdkPath
     * @param $androidVersion
     * @param $minSdkVersion
     */
    public function generate($gradleVersion, $sdkPath, $androidVersion, $minSdkVersion) {
        $this->output->write('Setting up project configuration');
        $this->generateFile('root.build.gradle', $this->path . '/build.gradle', [ 'gradleVersion' => $gradleVersion ]);
        $this->generateFile('gradle.properties', $this->path . '/gradle.properties');
        $this->generateFile('local.properties', $this->path . '/local.properties', [ 'sdkPath' => $sdkPath ]);
        $this->generateFile('settings.gradle', $this->path . '/settings.gradle', [ 'moduleName' => 'app' ]);
        $this->generateFile('proguard-rules.pro', $this->path.'/app/proguard-rules.pro');
        $this->generateFile('build.gradle', $this->path.'/app/build.gradle', [ 'androidVersion' => $androidVersion, 'minSdkVersion' => $minSdkVersion ]);
        $this->setup($this->path.'/gradle/wrapper/gradle-wrapper.properties', '/gradle-([\.?\d])+-all/', 'gradle-2.14.1-all');
        $this->output->writeln(' -> <info>OK</info>');
    }

    /**
     * Generate file (path) to target with params replacements
     *
     * @param $path
     * @param $target
     * @param array $params
     * @return void
     */
    private function generateFile($path, $target, $params=[]) {
        $params['package'] = $this->packageName;
        $this->renderFile($path.'.twig', $target, $params);
    }

    /**
     * Perform preg_replace in file
     *
     * @param $path
     * @param $pattern
     * @param $replacement
     * @return void
     */
    private function setup($path, $pattern, $replacement) {
        $content = file_get_contents($path);
        $content = preg_replace($pattern, $replacement, $content);
        file_put_contents($path, $content);
    }

}