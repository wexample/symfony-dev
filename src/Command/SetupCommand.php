<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Wexample\SymfonyHelpers\Helper\EnvironmentHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;

class SetupCommand extends AbstractDevCommand
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $env = $_ENV['CONTAINER_ENV'] ?? EnvironmentHelper::PROD;

        if (EnvironmentHelper::LOCAL === $env) {
            $vendorPath = $this->getCompanyVendorPath();

            // Get all the directories in the local vendor folder
            $this->forEachDevPackage(function (
                string $packageName
            ) use (
                $vendorPath,
                $io
            ) {
                $fs = new Filesystem();
                $localVendorPath = $this->getCompanyVendorLocalPath();
                $localPackagePath = FileHelper::joinPathParts([$localVendorPath, $packageName]);
                // Corresponding path in the vendor directory
                $vendorPackagePath = FileHelper::joinPathParts([$vendorPath, $packageName]);

                // Remove the existing directory in the vendor directory, if any
                if ($fs->exists($vendorPackagePath)) {
                    $fs->remove($vendorPackagePath);
                }

                // Create a symbolic link in the vendor directory to the local directory
                $fs->symlink($localPackagePath, $vendorPackagePath);

                $io->success('Created symlink from '.$vendorPackagePath.' to '.$vendorPackagePath);
            });

            $io->success('Local development environment is set up.');
        } else {
            $io->note('Skipping setup for non-local environment.');
        }

        $this->execCommand(
            'cache:clear',
            $output
        );

        return Command::SUCCESS;
    }
}
