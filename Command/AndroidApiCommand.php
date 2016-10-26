<?php
/**
 * Class GenerateAndroidApi
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Core command to create an RESTfull API for Android based on your Bundle
 *
 * This class define a new command "generate:android:api" in your application
 * for generating RESTFull Api for Android application all based on your entities defined in your bundle
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Command
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
class AndroidApiCommand extends ContainerAwareCommand {

    /**
     * Enable/Disable debug
     * @var bool
     */
    static $DEBUG = true;

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
            ->setName('android:create:app')
            ->setDescription('Generating RESTFull Api for Android application all based on your entities defined in your bundle')
            ->addArgument('bundle', InputArgument::REQUIRED, 'Target bundle');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

    }
}
