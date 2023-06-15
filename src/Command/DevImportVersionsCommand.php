<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyDev\Helper\DevHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;

#[AsCommand(
    name: 'dev:import-versions',
    description: 'Get the vendor-local last versions and place it in the app composer.json file',
)]
class DevImportVersionsCommand extends AbstractDevCommand
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $composerFilePath = FileHelper::joinPathParts([
            $this->kernel->getProjectDir(),
            DevHelper::COMPOSER_JSON_FILE_NAME,
        ]);

        $appConfig = json_decode(
            file_get_contents($composerFilePath)
        );

        $this->forEachDevPackage(function(
            string $packageName,
            object $config
        ) use
        (
            $appConfig,
            $io,
        ) {
            $packageName = $config->name;
            $appConfig->require->$packageName = '^'.$config->version;

            $io->info('App require now '.$config->name.' at version '.$config->version);
        });

        file_put_contents(
            $composerFilePath,
            json_encode($appConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $io->success('Updated '.$composerFilePath);

        return Command::SUCCESS;
    }
}
