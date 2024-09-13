<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class UseLocalRepo extends AbstractDevCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Imports local packages as path repositories into the composer.json file.');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $appConfig = self::getAppComposerConfig(JSON_OBJECT_AS_ARRAY);
        $appConfig['repositories'] ??= [];
        $localPackagesPaths = $this->bundleService->getAllLocalPackagesPaths();

        // Transform existing repositories into associative array
        $existingRepos = [];

        foreach ($appConfig['repositories'] as $repo) {
            $existingRepos[$repo['url']] = $repo;
        }

        // Loop over each local package path
        foreach ($localPackagesPaths as $packageName => $packagePath) {
            $group = dirname($packageName);

            $repository = [
                'type' => 'path',
                'url' => './vendor-local/'.$group.'/*',
            ];

            // If it doesn't exist, add the repository
            $existingRepos[$repository['url']] = $repository;

            $appConfig['config']['preferred-install'][$packageName] = 'source';
        }

        // Convert the associative array back to indexed array
        $appConfig['repositories'] = array_values($existingRepos);

        $this->writeAppComposerConfig(
            $appConfig
        );

        $io->success('Local packages imported in '.BundleHelper::COMPOSER_JSON_FILE_NAME);

        return Command::SUCCESS;
    }
}
