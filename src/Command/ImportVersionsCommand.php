<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class ImportVersionsCommand extends AbstractDevCommand
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $appConfig = self::getAppComposerConfig();

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

            $io->success('App require now '.$config->name.' at version '.$config->version);
        });

        $this->writeAppComposerConfig($appConfig, true);

        $io->success('Updated '.BundleHelper::COMPOSER_JSON_FILE_NAME);

        return Command::SUCCESS;
    }
}
