<?php
/**
 * Created by Benjamin Touchard @ 2016
 * Date: 19/10/16
 */

namespace Kolapsis\Bundle\AndroidGeneratorBundle\Generator;

use Kolapsis\Bundle\AndroidGeneratorBundle\Parser\BundleParser;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FileGenerator
 * Core class to clone files from skeleton files on resources.
 */
final class FileGenerator extends Generator {

    /**
     * Generate App Files base
     *
     * @param $appName
     * @param $apiUrl
     * @param BundleParser $parser
     */
    public function generate($appName, $apiUrl, BundleParser $parser) {
        $manifestPath = $this->path . '/app/src/main';
        $javaPath = $manifestPath . '/java/' . str_replace('.', '/', $this->packageName);
        $resPath = $manifestPath . '/res';

        $this->output->write('Preparing Android application base');

        $providerNames = $parser->providerNames();
        $entityNames = $parser->entityNames();
        $authorities = [
            'auth' => $parser->authorities(),
            'anonymous' => $parser->authorities(true)
        ];
        $userColumns = $parser->userColumns();

        $this->generateFile('AndroidManifest.xml', $manifestPath.'/AndroidManifest.xml', [
            'permissions' => ['INTERNET', 'AUTHENTICATE_ACCOUNTS', 'GET_ACCOUNTS', 'USE_CREDENTIALS', 'MANAGE_ACCOUNTS', 'WRITE_SYNC_SETTINGS', 'WRITE_SETTINGS', 'WRITE_EXTERNAL_STORAGE'],
            'providers' => $providerNames,
        ]);

        $this->generateFile('authenticator.xml', $resPath.'/xml/authenticator.xml', ['appName' => $appName]);
        foreach ($providerNames as $provider)
            $this->generateFile('syncadapter_provider.xml', $resPath.'/xml/syncadapter_'.$this->slug($provider).'.xml', ['provider' => $provider]);

        $this->generateFile('colors.xml', $resPath.'/values/colors.xml', ['appName' => $appName]);
        $this->generateFile('dimens.xml', $resPath.'/values/dimens.xml', ['appName' => $appName]);
        $this->generateFile('strings.xml', $resPath.'/values/strings.xml', ['appName' => $appName, 'providers' => $providerNames]);
        $this->generateFile('styles.xml', $resPath.'/values/styles.xml', ['appName' => $appName]);

        $this->generateFile('MainActivity.java', $javaPath.'/MainActivity.java', ['entities' => $entityNames]);
        $this->generateFile('Constants.java', $javaPath.'/Constants.java', ['appName' => $appName, 'apiUrl' => $apiUrl]);
        $this->generateFile('Entity.java', $javaPath.'/helpers/Entity.java');
        $this->generateFile('AccountHelper.java', $javaPath.'/helpers/AccountHelper.java', [
            'entities' => $entityNames,
            'authorities' => $authorities,
            'providers' => $parser->providerNames(true)
        ]);
        $this->generateFile('DatabaseHelper.java', $javaPath.'/helpers/DatabaseHelper.java', ['entities' => $entityNames]);
        $this->generateFile('BaseSyncAdapter.java', $javaPath.'/sync/BaseSyncAdapter.java');

        $this->generateFile('AccountActivity.java', $javaPath.'/authenticator/AccountActivity.java', ['providers' => $providerNames]);
        foreach (['Authenticator', 'AuthService'] as $class)
            $this->generateFile($class.'.java', $javaPath.'/authenticator/'.$class.'.java');
        $this->generateFile('account_auth.xml', $resPath.'/layout/account_auth.xml');

        $this->generateFile('Api.java', $javaPath.'/console/Api.java', ['columns' => $userColumns]);
        $this->generateFile('UserColumns.java', $javaPath.'/console/UserColumns.java', ['columns' => $userColumns]);

        foreach (['CloseUtils', 'BitmapUtils', 'FileUtils', 'HttpData', 'StringUtils'] as $class)
            $this->generateFile($class.'.java', $javaPath.'/utils/'.$class.'.java');
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
        //$target = $javaPath . '/' . $path . '.java';
        $params['package'] = $this->packageName;
        $this->renderFile($path.'.twig', $target, $params);
    }

    /**
     * Perform preg_replace in file
     *
     * @param $path
     * @param $pattern
     * @param $replacement
     */
    private function setupConf($path, $pattern, $replacement) {
        $content = file_get_contents($path);
        $content = preg_replace($pattern, $replacement, $content);
        file_put_contents($path, $content);
    }

    private function slug($subject) {
        return strtolower(preg_replace('/\B([A-Z])/', '_$1', $subject));
    }

}