<?php
/**
 * Created by IntelliJ IDEA.
 * User: benjamin
 * Date: 18/10/16
 * Time: 14:23
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Kolapsis\Bundle\AndroidGeneratorBundle\Generator\EntityGenerator;
use Kolapsis\Bundle\AndroidGeneratorBundle\Generator\FileGenerator;
use Kolapsis\Bundle\AndroidGeneratorBundle\Twig\TwigFormatterExtension;
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

class AndroidAppCommand extends ContainerAwareCommand
{

    private $twig;
    private $output;

    protected function configure()
    {
        $this
            ->setName('android:app:create')
            ->setDescription('Creates Android app.')
            ->setHelp("This command allows you to create an Android App from yor application...")
            ->addArgument('bundle', InputArgument::REQUIRED, 'Target bundle')
            ->addOption('android', 'a', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'App Android version', [24, 21])
            ->addOption('sdk', 's', InputOption::VALUE_REQUIRED, 'Android SDK path', '/opt/android-sdk')
            ->addOption('gradle', 'g', InputOption::VALUE_REQUIRED, 'Gradle version', '2.2.0')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $bundleName = $input->getArgument('bundle');

        list($androidVersion, $minSdkVersion) = $input->getOption('android');
        $sdkPath = $input->getOption('sdk');
        $gradleVersion = $input->getOption('gradle');

        $helper = $this->getHelper('question');
        $kernel = $this->getContainer()->get('kernel');
        $bundle = $kernel->getBundle($bundleName);
        if (empty($bundle)) {
            $output->writeln('<error>Error, '.$bundleName.' bundle doesn\'t exists !</error>');
            exit;
        }

        $skeletonDirs = $this->getSkeletonDirs($kernel, $bundle);
        $this->twig = $this->getTwigEnvironment($skeletonDirs);

        $output->writeln([
            '==================================',
            'Android App Generator',
            '==================================',
            '<info>Android API: '.$androidVersion.' (min: '.$minSdkVersion.')</info>',
            '<info>Android SDK path: '.$sdkPath.'</info>',
            '<info>Gradle: '.$gradleVersion.'</info>',
            '',
        ]);

        $appName = str_replace('Bundle', '', $bundle->getName());
        $question = new Question('Please enter the <info>App Name</info> ['.$appName.']: ', $appName);
        $appName = $helper->ask($input, $output, $question);

        // $path = dirname($kernel->getRootDir()) . '/android';
        $path = '/home/benjamin/Documents/workspace/' . $appName;
        $question = new Question('Please enter the <info>App final path</info> ['.$path.']: ', $path);
        $path = $helper->ask($input, $output, $question);

        $question = new Question('Please enter the <info>App domain name</info> [kolapsis.com]: ', 'kolapsis.com');
        $domainName = $helper->ask($input, $output, $question);

        //$apiUrl = 'http://' . preg_replace('/[\s_-]+/', '', strtolower($appName)) . '.' . $domainName;
        $apiUrl = 'http://192.168.0.28/FullApp/web/api';

        $question = new Question('Please enter the <info>App API url</info> ['.$apiUrl.']: ', $apiUrl);
        $apiUrl = $helper->ask($input, $output, $question);

        $tmp = explode('.', $domainName);
        $tmp = array_reverse($tmp);
        $packageName = implode('.', $tmp) . '.' . preg_replace('/[\s_-]+/', '', strtolower($appName));

        $manifestPath = $path . '/app/src/main';
        $javaPath = $manifestPath . '/java/' . str_replace('.', '/', $packageName);
        $resPath = $manifestPath . '/res';

        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
        $metadata = $manager->getBundleMetadata($bundle);

        $output->writeln('==================================');
        $output->writeln('');
        $output->writeln('Generating App: "' . $appName . '" [package: ' . $packageName . ']');
        $output->writeln('==================================');
        $output->writeln('');

        if (!is_dir($path)) {
            $this->output->write('Generate Android application');
            $process = new Process("android create project --target android-$androidVersion --activity MainActivity --package $packageName --gradle --gradle-version $gradleVersion --path $path");
            $process->run();
            $this->output->writeln(' -> OK');

            $fs = new Filesystem();
            $this->output->write('Prepare project structure');
            $fs->mkdir($path . '/app');
            $fs->mkdir($path . '/app/libs');
            $fs->rename($path . '/src', $path . '/app/src');
            $this->output->writeln(' -> <info>OK</info>');
        }

        $fileGenerator = new FileGenerator($this->twig, $packageName);

        $this->output->write('Setting up project configuration');
        $fileGenerator->generate('root.build.gradle', $path . '/build.gradle', [ 'gradleVersion' => $gradleVersion ]);
        $fileGenerator->generate('gradle.properties', $path . '/gradle.properties');
        $fileGenerator->generate('local.properties', $path . '/local.properties', [ 'sdkPath' => $sdkPath ]);
        $fileGenerator->generate('settings.gradle', $path . '/settings.gradle', [ 'moduleName' => 'app' ]);
        $fileGenerator->generate('proguard-rules.pro', $path.'/app/proguard-rules.pro');
        $fileGenerator->generate('build.gradle', $path.'/app/build.gradle', [ 'androidVersion' => $androidVersion, 'minSdkVersion' => $minSdkVersion ]);
        $fileGenerator->setupConf($path.'/gradle/wrapper/gradle-wrapper.properties', '/gradle-([\.?\d])+-all/', 'gradle-2.14.1-all');
        $this->output->writeln(' -> <info>OK</info>');

        $this->output->write('Parsing: ' . $metadata->getNamespace());
        $entityGenerator = new EntityGenerator($this->twig, $this->output, $packageName, $javaPath);
        $entityGenerator->prepare($metadata);
        $providerNames = $entityGenerator->extractProviderNames();
        $entityNames = $entityGenerator->extractEntityNames();
        $this->output->writeln(' -> <info>OK</info>');

        $this->output->write('Preparing Android application base');

        $fileGenerator->generate('AndroidManifest.xml', $manifestPath.'/AndroidManifest.xml', [
            'permissions' => ['INTERNET', 'AUTHENTICATE_ACCOUNTS', 'GET_ACCOUNTS', 'USE_CREDENTIALS', 'MANAGE_ACCOUNTS', 'WRITE_SYNC_SETTINGS', 'WRITE_SETTINGS', 'WRITE_EXTERNAL_STORAGE'],
            'providers' => $providerNames,
        ]);

        $fileGenerator->generate('authenticator.xml', $resPath.'/xml/authenticator.xml', ['appName' => $appName]);
        foreach ($providerNames as $provider)
            $fileGenerator->generate('syncadapter_provider.xml', $resPath.'/xml/syncadapter_'.strtolower($provider).'.xml', ['provider' => $provider]);

        $fileGenerator->generate('colors.xml', $resPath.'/values/colors.xml', ['appName' => $appName]);
        $fileGenerator->generate('dimens.xml', $resPath.'/values/dimens.xml', ['appName' => $appName]);
        $fileGenerator->generate('strings_auth.xml', $resPath.'/values/strings_auth.xml', ['appName' => $appName, 'providers' => $providerNames]);
        $fileGenerator->generate('styles.xml', $resPath.'/values/styles.xml', ['appName' => $appName]);

        $fileGenerator->generate('Constants.java', $javaPath.'/Constants.java', ['appName' => $appName, 'apiUrl' => $apiUrl]);
        $fileGenerator->generate('Entity.java', $javaPath.'/helpers/Entity.java');
        $fileGenerator->generate('AccountHelper.java', $javaPath.'/helpers/AccountHelper.java', ['entities' => $entityNames, 'authorities' => $this->getAuthorities($providerNames)]);
        $fileGenerator->generate('DatabaseHelper.java', $javaPath.'/helpers/DatabaseHelper.java', ['entities' => $entityNames]);
        $fileGenerator->generate('BaseSyncAdapter.java', $javaPath.'/sync/BaseSyncAdapter.java');

        $fileGenerator->generate('AccountActivity.java', $javaPath.'/authenticator/AccountActivity.java', ['providers' => $providerNames]);
        foreach (['Authenticator', 'AuthService'] as $class)
            $fileGenerator->generate($class.'.java', $javaPath.'/authenticator/'.$class.'.java');
        $fileGenerator->generate('account_auth.xml', $resPath.'/layout/account_auth.xml');

        $userColumns = $this->getUserColumns($manager);
        $fileGenerator->generate('Api.java', $javaPath.'/console/Api.java', ['columns' => $userColumns]); //, ['providers' => $providerNames, 'entities' => $entityNames]
        $fileGenerator->generate('UserColumns.java', $javaPath.'/console/UserColumns.java', ['columns' => $userColumns]);

        foreach (['CloseUtils', 'BitmapUtils', 'FileUtils', 'HttpData', 'StringUtils'] as $class)
            $fileGenerator->generate($class.'.java', $javaPath.'/utils/'.$class.'.java');
        $this->output->writeln(' -> <info>OK</info>');

        $entityGenerator->generate();

        $this->output->writeln('');
        $this->output->writeln('<comment>Finished !</comment>');
    }

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

    private function getAuthorities($names) {
        $auths = [];
        foreach ($names as $name)
            $auths[] = $name . 'Provider.AUTHORITY';
        return implode(', ', $auths);
    }

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

    private function getSkeletonSubDirs($dir, &$skeletonDirs) {
        $finder = new Finder();
        $finder->directories()->in($dir);
        foreach ($finder as $sub) {
            $skeletonDirs[] = $sub->getPathname();
        }
    }

    private function getUserColumns($manager) {
        $columns = [];
        $class = $this->getContainer()->get('fos_user.user_manager')->getClass();
        if ($class != null) {
            $meta = $manager->getClassMetadata($class);
            $excludes = ['id'];
            $columns = array_diff($meta->getMetaData()[0]->getColumnNames(), $excludes);
        }
        return $columns;
    }
}