<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyDev\Command\Traits\WithArgPackage;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class ImportVersionsCommand extends AbstractDevCommand
{
    use WithArgPackage;

    public function getDescription(): string
    {
        return "Import latest custom packages versions to current composer configuration";
    }

    protected function configure(): void
    {
        $this->configurePackageArg();
        $this->addOption(
            'source',
            's',
            InputOption::VALUE_OPTIONAL,
            'Source to check installed packages: lock (composer.lock) or json (composer.json)',
            'lock'
        );
    }

    protected function getInstalledPackages(
        InputInterface $input,
        SymfonyStyle $io
    ): array {
        $source = $input->getOption('source');
        $composerDir = dirname($this->getAppComposerConfigPath());
        $installedPackages = [];

        // Try composer.lock first if selected or if source is not specified
        if ($source === 'lock') {
            $composerLockPath = $composerDir . '/composer.lock';
            if (file_exists($composerLockPath)) {
                $composerLock = json_decode(file_get_contents($composerLockPath));

                // Get all installed packages from lock file
                foreach ($composerLock->packages as $package) {
                    $installedPackages[$package->name] = true;
                }

                if (isset($composerLock->{'packages-dev'})) {
                    foreach ($composerLock->{'packages-dev'} as $package) {
                        $installedPackages[$package->name] = true;
                    }
                }

                return $installedPackages;
            }

            $io->warning('composer.lock not found, falling back to composer.json');
        }

        // Use composer.json if lock is not available or if explicitly selected
        $composerJsonPath = $composerDir . '/composer.json';
        if (! file_exists($composerJsonPath)) {
            $io->error('Neither composer.lock nor composer.json found. Please check your project configuration.');

            return [];
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath));

        // Get packages from require and require-dev
        if (isset($composerJson->require)) {
            foreach ($composerJson->require as $package => $version) {
                $installedPackages[$package] = true;
            }
        }

        if (isset($composerJson->{'require-dev'})) {
            foreach ($composerJson->{'require-dev'} as $package => $version) {
                $installedPackages[$package] = true;
            }
        }

        return $installedPackages;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $filterPackageName = $this->getPackageArg($input);
        $appConfig = self::getAppComposerConfig();

        $installedPackages = $this->getInstalledPackages($input, $io);
        if (empty($installedPackages)) {
            return Command::FAILURE;
        }

        $this->forEachDevPackage(function (
            string $vendorName,
            string $packageName,
            string $localPackagePath,
            object $config
        ) use (
            $appConfig,
            $io,
            $filterPackageName,
            $installedPackages
        ) {
            // Only update if package is installed and matches filter if any
            $packageFullName = $config->name;
            if (
                (! $filterPackageName || $filterPackageName === $packageFullName)
                && isset($installedPackages[$packageFullName])
            ) {
                $appConfig->require->$packageFullName = '^'.$config->version;

                $io->success('App require now '.$packageFullName.' at version '.$config->version);
            }
        });

        $this->writeAppComposerConfig($appConfig, true);

        $io->success('Updated '.BundleHelper::COMPOSER_JSON_FILE_NAME);

        return Command::SUCCESS;
    }
}
