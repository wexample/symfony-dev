<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyDev\Command\Traits\WithArgPackage;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class ImportVersionsCommand extends AbstractDevCommand
{
    use WithArgPackage;

    protected function configure(): void
    {
        $this->configurePackageArg();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $filterPackageName = $this->getPackageArg($input);
        $appConfig = self::getAppComposerConfig();

        $this->forEachDevPackage(function (
            string $packageName,
            object $config
        ) use (
            $appConfig,
            $io,
            $filterPackageName
        ) {
            if (!$filterPackageName || $filterPackageName === $config->name) {
                $packageName = $config->name;
                $appConfig->require->$packageName = '^'.$config->version;

                $io->success('App require now '.$config->name.' at version '.$config->version);
            }
        });

        $this->writeAppComposerConfig($appConfig, true);

        $io->success('Updated '.BundleHelper::COMPOSER_JSON_FILE_NAME);

        return Command::SUCCESS;
    }
}
