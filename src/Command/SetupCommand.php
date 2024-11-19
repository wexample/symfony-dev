<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Wexample\SymfonyHelpers\Helper\EnvironmentHelper;
use Wexample\SymfonyHelpers\Helper\PathHelper;

class SetupCommand extends AbstractDevCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Sets up the local development environment by creating symlinks for local packages in the vendor directory.');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $env = $_ENV['APP_HOST_ENV'] ?? EnvironmentHelper::PROD;

        if (EnvironmentHelper::LOCAL === $env) {
            $vendorPath = $this->getCompanyVendorPath();
            $localVendorPath = $this->getCompanyVendorLocalPath();
            $fs = new Filesystem();

            // Get all the directories in the local vendor folder
            $this->forEachDevPackage(function(
                string $packageName
            ) use
            (
                $vendorPath,
                $localVendorPath,
                $fs,
                $io
            ) {
                $localPackagePath = PathHelper::join([$localVendorPath, $packageName]);
                $vendorPackagePath = PathHelper::join([$vendorPath, $packageName]);

                // Remove the existing directory in the vendor directory, if any
                if ($fs->exists($vendorPackagePath)) {
                    $fs->remove($vendorPackagePath);
                }

                // Create a symbolic link in the vendor directory to the local directory
                $fs->symlink($localPackagePath, $vendorPackagePath);

                $io->success('Created symlink from '.$localPackagePath.' to '.$vendorPackagePath);
            });

            $io->success('Local development environment is set up.');
        } else {
            $io->note('Skipping setup for non-local environment: '
                .$env
                .'. Set APP_HOST_ENV=local in .env.local file to define development environment');
        }

        $this->execCommand(
            'cache:clear',
            $output
        );

        return Command::SUCCESS;
    }
}
