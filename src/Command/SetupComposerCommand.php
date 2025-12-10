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
    private ?string $composerLockBackupPath = null;

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

        $io->section('Step 1: Backup original composer.json and composer.lock');
        $this->backupComposerJson($io);
        $this->backupComposerLock($io);

        $io->section('Step 2: Inject local path repositories into composer.json');
        $packagesToReplace = $this->injectLocalRepositories($io);

        if (empty($packagesToReplace)) {
            $io->warning('No local repositories found to inject. Restoring files.');
            $this->restoreComposerJson($io);
            $this->restoreComposerLock($io);
            return Command::SUCCESS;
        }

        $io->section('Step 3: Remove existing packages from vendor/');
        $this->removeExistingPackages($io, $packagesToReplace);

        $io->section('Step 4: Temporarily remove composer.lock');
        $this->removeComposerLock($io);

        $io->section('Step 5: Run composer install to create symlinks');
        $success = $this->runComposerInstall($io);

        $io->section('Step 6: Restore original composer.json and composer.lock');
        $this->restoreComposerJson($io);
        $this->restoreComposerLock($io);

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

    private function backupComposerLock(SymfonyStyle $io): void
    {
        $composerLockPath = $this->kernel->getProjectDir().'/composer.lock';
        
        if (!file_exists($composerLockPath)) {
            $io->writeln('⊘ No composer.lock to backup');
            return;
        }

        $this->composerLockBackupPath = $composerLockPath . '.backup';
        copy($composerLockPath, $this->composerLockBackupPath);
        $io->writeln('✓ composer.lock backed up to composer.lock.backup');
    }

    private function removeComposerLock(SymfonyStyle $io): void
    {
        $composerLockPath = $this->kernel->getProjectDir().'/composer.lock';
        
        if (file_exists($composerLockPath)) {
            unlink($composerLockPath);
            $io->writeln('✓ composer.lock temporarily removed');
        } else {
            $io->writeln('⊘ No composer.lock to remove');
        }
    }

    private function injectLocalRepositories(SymfonyStyle $io): array
    {
        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        $data = json_decode($this->composerJsonBackup, true);

        if (!isset($data['repositories'])) {
            $data['repositories'] = [];
        }

        $packagesToReplace = [];
        $repositoriesAdded = [];

        // Process each configured vendor dev path pattern
        foreach ($this->vendorDevPaths as $pattern) {
            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];

            if (empty($matches)) {
                $io->writeln("⚠ No directories found for pattern: {$pattern}");
                continue;
            }

            // Group packages by parent directory to use wildcard pattern
            $packagesByParent = [];
            foreach ($matches as $packagePath) {
                // Verify this is a valid Composer package
                if (!file_exists($packagePath.'/composer.json')) {
                    $io->writeln("⊘ Skipping {$packagePath}: no composer.json found");
                    continue;
                }

                $parentDir = dirname($packagePath);
                if (!isset($packagesByParent[$parentDir])) {
                    $packagesByParent[$parentDir] = [];
                }
                
                $vendorName = basename(dirname($packagePath));
                $packageName = basename($packagePath);
                
                $packagesByParent[$parentDir][] = [
                    'vendor' => $vendorName,
                    'package' => $packageName,
                    'path' => $packagePath,
                ];
            }

            // Add one repository per parent directory with wildcard pattern
            foreach ($packagesByParent as $parentDir => $packages) {
                $repositoryUrl = $parentDir . '/*';
                
                // Avoid duplicate repositories
                if (in_array($repositoryUrl, $repositoriesAdded)) {
                    continue;
                }
                
                $data['repositories'][] = [
                    'type' => 'path',
                    'url' => $repositoryUrl,
                    'options' => [
                        'symlink' => true,
                    ],
                ];
                
                $repositoriesAdded[] = $repositoryUrl;
                $io->writeln("→ Added repository: {$repositoryUrl}");
                
                // Track packages for removal
                foreach ($packages as $package) {
                    $packagesToReplace[] = [
                        'vendor' => $package['vendor'],
                        'package' => $package['package'],
                    ];
                    $io->writeln("  └─ {$package['vendor']}/{$package['package']}");
                }
            }
        }

        // Write modified composer.json
        file_put_contents(
            $composerJsonPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $io->writeln("✓ ".count($repositoriesAdded)." path repositories with ".count($packagesToReplace)." packages");

        return $packagesToReplace;
    }

    private function removeExistingPackages(SymfonyStyle $io, array $packagesToReplace): void
    {
        $vendorDir = $this->kernel->getProjectDir().'/vendor';
        $removedCount = 0;

        foreach ($packagesToReplace as $package) {
            $packagePath = "{$vendorDir}/{$package['vendor']}/{$package['package']}";

            if (!file_exists($packagePath)) {
                $io->writeln("⊘ Package not installed: {$package['vendor']}/{$package['package']}");
                continue;
            }

            // Don't remove if it's already a symlink
            if (is_link($packagePath)) {
                $io->writeln("→ Already a symlink: {$package['vendor']}/{$package['package']}");
                continue;
            }

            // Remove the directory
            $this->removeDirectory($packagePath);
            $io->writeln("✓ Removed: {$package['vendor']}/{$package['package']}");
            $removedCount++;
        }

        $io->writeln("✓ {$removedCount} package(s) removed from vendor/");
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function runComposerInstall(SymfonyStyle $io): bool
    {
        $io->note([
            'Running composer install without composer.lock.',
            'Composer will resolve dependencies and create symlinks from local path repositories.',
            'After this, the original composer.lock will be restored.',
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
            $io->error('No composer.json backup available to restore!');
            return;
        }

        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        file_put_contents($composerJsonPath, $this->composerJsonBackup);
        $io->writeln('✓ Original composer.json restored');
    }

    private function restoreComposerLock(SymfonyStyle $io): void
    {
        if ($this->composerLockBackupPath === null || !file_exists($this->composerLockBackupPath)) {
            $io->writeln('⊘ No composer.lock backup to restore');
            return;
        }

        $composerLockPath = $this->kernel->getProjectDir().'/composer.lock';
        
        // Remove the lock file that was generated by composer install
        if (file_exists($composerLockPath)) {
            unlink($composerLockPath);
        }
        
        // Restore the original lock file
        rename($this->composerLockBackupPath, $composerLockPath);
        $io->writeln('✓ Original composer.lock restored');
    }
}
