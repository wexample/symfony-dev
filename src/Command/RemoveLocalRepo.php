<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class RemoveLocalRepo extends AbstractDevCommand
{
    public function getDescription(): string
    {
        return 'Remove local packages as path repositories into the composer.json file.';
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $appConfig = self::getAppComposerConfig(JSON_OBJECT_AS_ARRAY);
        $localPackagesPaths = $this->bundleService->getAllLocalPackagesPaths();

        // Transform existing repositories into associative array
        $existingRepos = [];
        foreach ($appConfig['repositories'] as $repo) {
            $existingRepos[$repo['url']] = $repo;
        }

        // Loop over each local package path
        foreach ($localPackagesPaths as $packageName => $packagePath) {
            $group = dirname($packageName);

            $repositoryUrl = './vendor-local/'.$group.'/*';

            // If it exists, remove the repository
            if (isset($existingRepos[$repositoryUrl])) {
                unset($existingRepos[$repositoryUrl]);
            }

            // If it exists, remove the preferred-install config
            if (isset($appConfig['config']['preferred-install'][$packageName])) {
                unset($appConfig['config']['preferred-install'][$packageName]);
            }
        }

        // Convert the associative array back to indexed array
        $appConfig['repositories'] = array_values($existingRepos);

        if (empty($appConfig['config']['preferred-install'])) {
            unset($appConfig['config']['preferred-install']);
        }

        if (empty($appConfig['repositories'])) {
            unset($appConfig['repositories']);
        }

        $this->writeAppComposerConfig(
            $appConfig
        );

        $io->success('Local packages removed from '.BundleHelper::COMPOSER_JSON_FILE_NAME);

        return Command::SUCCESS;
    }
}
