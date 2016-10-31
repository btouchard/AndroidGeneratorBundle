<?php
/**
 * Class AndroidAppCommand
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Command;


use Kolapsis\Bundle\AndroidGeneratorBundle\Generator\EntityGenerator;
use Kolapsis\Bundle\AndroidGeneratorBundle\Generator\FileGenerator;
use Kolapsis\Bundle\AndroidGeneratorBundle\Generator\GradleGenerator;
use Kolapsis\Bundle\AndroidGeneratorBundle\Parser\BundleParser;
use Kolapsis\Bundle\AndroidGeneratorBundle\Utils\TwigFormatterExtension;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

/**
 * Core command to create an Android project based on your Bundle
 *
 * This class define a new command "generate:android:app" in your application
 * for generating Android application with account and authentication, content providers and sync system all based on your entities defined in your bundle
 *
 * @package Kolapsis\Bundle\AndroidGeneratorBundle\Command
 * @author Benjamin Touchard <benjamin@kolapsis.com>
 */
final class AndroidAppCommand extends ContainerAwareCommand {

    /**
     * Enable/Disable debug
     * @var bool
     */
    private static $DEBUG = false;

    /**
     * Twig environment
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * Command Line Output
     * @var OutputInterface
     */
    private $output;

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
            ->setName('generate:android:app')
            ->setDescription('Generate/update Android application from Bundle.')
            ->setHelp("Generating Android application with account and authentication, content providers and sync system all based on your entities defined in your bundle")
            ->addArgument('bundle', InputArgument::REQUIRED, 'Target bundle')
            ->addOption('target_sdk', 't', InputOption::VALUE_REQUIRED, 'Android target SDK', 24)
            ->addOption('min_sdk', 'm', InputOption::VALUE_REQUIRED, 'Android target SDK min version', 21)
            ->addOption('sdk_path', 's', InputOption::VALUE_REQUIRED, 'Android SDK path', '/opt/android-sdk')
            ->addOption('gradle_version', 'g', InputOption::VALUE_REQUIRED, 'Gradle version', '2.2.0')
            ->addOption('app_name', 'a', InputOption::VALUE_REQUIRED, 'App name')
            ->addOption('app_path', 'p', InputOption::VALUE_REQUIRED, 'App path')
            ->addOption('app_domain', 'd', InputOption::VALUE_REQUIRED, 'App domain name')
            ->addOption('app_package', 'k', InputOption::VALUE_REQUIRED, 'App package name')
            ->addOption('api_url', 'u', InputOption::VALUE_REQUIRED, 'Api url')
            ->addOption('no_build_confirm', 'y', InputOption::VALUE_REQUIRED, 'No build confirm', false)
        ;
    }

    /**
     * {@inheritdoc}
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->output = $output;
        $bundleName = $input->getArgument('bundle');

        $androidVersion = $input->getOption('target_sdk');
        $minSdkVersion = $input->getOption('min_sdk');
        $sdkPath = $input->getOption('sdk_path');
        $gradleVersion = $input->getOption('gradle_version');
        $appName = $input->getOption('app_name');
        $path = $input->getOption('app_path');
        $domainName = $input->getOption('app_domain');
        $packageName = $input->getOption('app_package');
        $apiUrl = $input->getOption('api_url');
        $build = $input->getOption('no_build_confirm') !== false;

        $helper = $this->getHelper('question');
        $kernel = $this->getContainer()->get('kernel');
        $bundle = $kernel->getBundle($bundleName);
        if (empty($bundle)) {
            $output->writeln('<error>Error, '.$bundleName.' bundle doesn\'t exists !</error>');
            exit;
        }

        if (empty($appName)) {
            $appName = str_replace('Bundle', '', $bundle->getName());
            $question = new Question('Please enter the <info>App Name</info> [' . $appName . ']: ', $appName);
            $appName = $helper->ask($input, $output, $question);
        }

        if (empty($path)) {
            if (AndroidAppCommand::$DEBUG) $path = '/home/benjamin/Documents/workspace/' . $appName;
            else $path = dirname($kernel->getRootDir()) . '/android/' . $appName;
            $question = new Question('Please enter the <info>App path</info> [' . $path . ']: ', $path);
            $path = $helper->ask($input, $output, $question);
        }

        if (empty($domainName)) {
            $question = new Question('Please enter the <info>App domain name</info> [kolapsis.com]: ', 'kolapsis.com');
            $domainName = $helper->ask($input, $output, $question);
        }

        if (empty($apiUrl)) {
            if (AndroidAppCommand::$DEBUG) $apiUrl = 'http://192.168.0.28/FullApp/web/api';
            else $apiUrl = 'http://' . preg_replace('/[\s_-]+/', '', strtolower($appName)) . '.' . $domainName;
            $question = new Question('Please enter the <info>App API url</info> [' . $apiUrl . ']: ', $apiUrl);
            $apiUrl = $helper->ask($input, $output, $question);
        }

        if (empty($packageName)) {
            $tmp = explode('.', $domainName);
            $tmp = array_reverse($tmp);
            $packageName = implode('.', $tmp) . '.' . preg_replace('/[\s_-]+/', '', strtolower($appName));
        }


        $output->writeln([
            '==================================',
            'Android App Generator',
            '==================================',
            '<info>Android API: '.$androidVersion.' (min: '.$minSdkVersion.')</info>',
            '<info>Android SDK path: '.$sdkPath.'</info>',
            '<info>Gradle: '.$gradleVersion.'</info>',
            '----------------------------------',
            '<info>App name: '.$appName.'</info>',
            '<info>App path: '.$path.'</info>',
            '<info>Package: '.$packageName.'</info>',
            '<info>API url: '.$apiUrl.'</info>',
            '',
        ]);

        if (!$build) {
            $question = new Question('<question>This is correct ? [Y/n]</question>', 'Y');
            $build = $helper->ask($input, $output, $question) === 'Y';
        }

        if (!$build) {
            $this->output->writeln('<comment>Bye</comment>');
            exit;
        }

        $skeletonDirs = $this->getSkeletonDirs($kernel, $bundle);
        $this->twig = $this->getTwigEnvironment($skeletonDirs);

        $parser = new BundleParser($this->output, $this->getContainer());
        $parser->parse($bundle);

        if (!is_dir($path)) {
            $this->output->write('Generate Android application');
            $process = new Process("android create project --target android-$androidVersion --activity MainActivity --package $packageName --gradle --gradle-version $gradleVersion --path $path");
            $process->run();
            $this->output->writeln(' -> <info>OK</info>');

            $fs = new Filesystem();
            $this->output->write('Prepare project structure');
            $fs->mkdir($path . '/app');
            $fs->mkdir($path . '/app/libs');
            $fs->rename($path . '/src', $path . '/app/src');
            $this->output->writeln(' -> <info>OK</info>');
        }

        $entityGenerator = new EntityGenerator($this->twig, $this->output, $packageName, $path);
        $gradleGenerator = new GradleGenerator($this->twig, $this->output, $packageName, $path);
        $fileGenerator = new FileGenerator($this->twig, $this->output, $packageName, $path);

        $gradleGenerator->generate($gradleVersion, $sdkPath, $androidVersion, $minSdkVersion);
        $fileGenerator->generate($appName, $apiUrl, $parser);
        $entityGenerator->generate($parser);

        $this->output->writeln('');
        $this->output->writeln('<comment>Finished !</comment>');
    }

    /**
     * Return personalized Twig environment
     * @param array $skeletonDirs
     * @return \Twig_Environment
     */
    private function getTwigEnvironment($skeletonDirs) {
        $env = new \Twig_Environment(new \Twig_Loader_Filesystem($skeletonDirs), array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));
        $env->addExtension(new TwigFormatterExtension());
        return $env;
    }

    /**
     * Read skeleton dir for Twig
     * @param KernelInterface $kernel
     * @param BundleInterface|null $bundle
     * @return array
     */
    private function getSkeletonDirs(KernelInterface $kernel, BundleInterface $bundle = null) {
        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/skeleton')) {
            $skeletonDirs[] = $dir;
            // $this->getSkeletonSubDirs($dir, $skeletonDirs);
        }

        if (is_dir($dir = $kernel->getRootdir().'/Resources/AndroidGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
            // $this->getSkeletonSubDirs($dir, $skeletonDirs);
        }

        $dir = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = $dir;
        $this->getSkeletonSubDirs($dir, $skeletonDirs);
        $skeletonDirs[] = __DIR__ . '/../Resources';

        return $skeletonDirs;
    }

    /**
     * Read skeleton sub dir
     * @param string $dir
     * @param array $skeletonDirs
     */
    private function getSkeletonSubDirs($dir, &$skeletonDirs) {
        $finder = new Finder();
        $finder->directories()->in($dir);
        foreach ($finder as $sub) {
            $skeletonDirs[] = $sub->getPathname();
        }
    }
}