<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

/**
 * SetupComposerCommand
 * 
 * PURPOSE:
 * Install local development packages as symlinks in vendor/ directory for development.
 * 
 * PROBLEM:
 * - We want to use local package versions during development (e.g., /var/www/vendor-dev/wexample/*)
 * - Composer natively supports local "path" repositories with symlink option
 * - BUT adding them to composer.json creates a "dirty" composer.lock that breaks production
 * 
 * SOLUTION:
 * 1. Backup the original composer.json (production-ready)
 * 2. Temporarily inject local "path" repositories into composer.json
 * 3. Run `composer install` which:
 *    - Respects composer.lock (no changes to lock file)
 *    - Replaces installed packages with symlinks to local paths
 * 4. Restore original composer.json
 * 
 * RESULT:
 * - composer.json and composer.lock remain production-ready
 * - vendor/ contains symlinks to local development packages
 * - Changes to local packages are immediately reflected in the application
 */
class SetupComposerCommand extends AbstractDevCommand
{
    private array $vendorDevPaths = [];
    private ?string $composerJsonBackup = null;

    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        if ($parameterBag && $parameterBag->has('wexample_symfony_dev.vendor_dev_paths')) {
            $this->vendorDevPaths = (array) $parameterBag->get('wexample_symfony_dev.vendor_dev_paths');
        }

        parent::__construct($kernel, $bundleService, $parameterBag, $name);
    }

    public function getDescription(): string
    {
        return 'Install local development packages as symlinks using Composer path repositories.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->vendorDevPaths)) {
            $io->error('No vendor_dev_paths configured. Please configure wexample_symfony_dev.vendor_dev_paths in your config.');
            return Command::FAILURE;
        }

        $io->section('Step 1: Backup original composer.json');
        $this->backupComposerJson($io);

        $io->section('Step 2: Inject local path repositories into composer.json');
        $repositoriesAdded = $this->injectLocalRepositories($io);

        if ($repositoriesAdded === 0) {
            $io->warning('No local repositories found to inject. Restoring composer.json.');
            $this->restoreComposerJson($io);
            return Command::SUCCESS;
        }

        $io->section('Step 3: Run composer install to create symlinks');
        $success = $this->runComposerInstall($io);

        $io->section('Step 4: Restore original composer.json');
        $this->restoreComposerJson($io);

        if ($success) {
            $io->success('Local development packages installed as symlinks successfully.');
            return Command::SUCCESS;
        } else {
            $io->error('Composer install failed. Check the output above for details.');
            return Command::FAILURE;
        }
    }

    private function backupComposerJson(SymfonyStyle $io): void
    {
        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        $this->composerJsonBackup = file_get_contents($composerJsonPath);
        $io->writeln('✓ composer.json backed up');
    }

    private function injectLocalRepositories(SymfonyStyle $io): int
    {
        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        $data = json_decode($this->composerJsonBackup, true);

        if (!isset($data['repositories'])) {
            $data['repositories'] = [];
        }

        $repositoriesAdded = 0;

        // Process each configured vendor dev path pattern
        foreach ($this->vendorDevPaths as $pattern) {
            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];

            if (empty($matches)) {
                $io->writeln("⚠ No directories found for pattern: {$pattern}");
                continue;
            }

            foreach ($matches as $packagePath) {
                // Verify this is a valid Composer package
                if (!file_exists($packagePath.'/composer.json')) {
                    $io->writeln("⊘ Skipping {$packagePath}: no composer.json found");
                    continue;
                }

                // Add path repository with symlink option
                $data['repositories'][] = [
                    'type' => 'path',
                    'url' => $packagePath,
                    'options' => ['symlink' => true],
                ];

                $vendorName = basename(dirname($packagePath));
                $packageName = basename($packagePath);
                $io->writeln("→ Added repository: {$vendorName}/{$packageName} ({$packagePath})");
                $repositoriesAdded++;
            }
        }

        // Write modified composer.json
        file_put_contents(
            $composerJsonPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $io->writeln("✓ {$repositoriesAdded} local repositories injected into composer.json");

        return $repositoriesAdded;
    }

    private function runComposerInstall(SymfonyStyle $io): bool
    {
        $io->note([
            'Running composer install with temporary composer.json containing local path repositories.',
            'Composer will display warnings about composer.lock mismatch - THIS IS EXPECTED.',
            'The lock file will NOT be modified because composer install always respects it.',
            'Composer will replace packages in vendor/ with symlinks to local paths.',
        ]);

        $projectDir = $this->kernel->getProjectDir();
        $cmd = "cd {$projectDir} && composer install --no-interaction 2>&1";
        
        $io->writeln("Running: composer install");
        exec($cmd, $output, $exitCode);

        foreach ($output as $line) {
            $io->writeln("  {$line}");
        }

        return $exitCode === 0;
    }

    private function restoreComposerJson(SymfonyStyle $io): void
    {
        if ($this->composerJsonBackup === null) {
            $io->error('No backup available to restore!');
            return;
        }

        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        file_put_contents($composerJsonPath, $this->composerJsonBackup);
        $io->writeln('✓ Original composer.json restored');
    }
}
