<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class SetupCommand extends AbstractDevCommand
{
    private array $vendorDevPaths = [];
    private ?string $composerJsonBackup;

    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        // Get vendor dev paths from config
        if ($parameterBag && $parameterBag->has('wexample_symfony_dev.vendor_dev_paths')) {
            $this->vendorDevPaths = (array) $parameterBag->get('wexample_symfony_dev.vendor_dev_paths');
        }

        parent::__construct($kernel, $bundleService, $parameterBag, $name);
    }

    public function getDescription(): string
    {
        return 'Sets up the development environment by creating symlinks for local packages in the vendor directory.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Setting up node_modules symlinks for dev packages');
        $this->setupNodeModulesSymlinks($io);

        $io->section('Configuring local Composer path repositories (temporary)');
        $this->applyLocalComposerRepositories($io);

        $io->section('Running Composer install using local repositories');
        $this->runComposerInstallWithLocalRepos($io);

        $io->section('Restoring original composer.json');
        $this->restoreComposerJson($io);

        $io->section('Clearing Symfony cache');
        $this->execCommand('cache:clear', $output);

        return Command::SUCCESS;
    }

    private function setupNodeModulesSymlinks(SymfonyStyle $io): void
    {
        $nodeModulesPath = $this->kernel->getProjectDir().'/node_modules';

        if (!is_dir($nodeModulesPath)) {
            $io->warning('node_modules directory not found. Run yarn install first.');
            return;
        }

        $symlinkCount = 0;

        foreach ($this->vendorDevPaths as $pattern) {
            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $packagePath) {
                $packageJsonPath = $packagePath.'/assets/package.json';
                if (!is_file($packageJsonPath)) {
                    continue;
                }

                $symlinkPath = $packagePath.'/node_modules';

                if (is_link($symlinkPath)) {
                    unlink($symlinkPath);
                } elseif (is_dir($symlinkPath)) {
                    $io->warning("Skipping {$packagePath}: node_modules is a real directory");
                    continue;
                }

                if (symlink($nodeModulesPath, $symlinkPath)) {
                    $io->writeln("✓ Linked {$symlinkPath} → {$nodeModulesPath}");
                    $symlinkCount++;
                } else {
                    $io->error("Failed to create symlink: {$symlinkPath}");
                }
            }
        }

        $io->success("Created {$symlinkCount} symlink(s)");
    }

    private function applyLocalComposerRepositories(SymfonyStyle $io): void
    {
        $composerJson = $this->kernel->getProjectDir().'/composer.json';
        $this->composerJsonBackup = file_get_contents($composerJson);

        $data = json_decode($this->composerJsonBackup, true);

        if (!isset($data['repositories'])) {
            $data['repositories'] = [];
        }

        foreach ($this->vendorDevPaths as $pattern) {
            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $packagePath) {
                $repo = [
                    'type' => 'path',
                    'url' => $packagePath,
                    'options' => ['symlink' => true],
                ];

                $data['repositories'][] = $repo;

                $io->writeln("→ Added local repository: {$packagePath}");
            }
        }

        file_put_contents($composerJson, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function runComposerInstallWithLocalRepos(SymfonyStyle $io): void
    {
        $cmd = 'composer install --no-interaction';
        $io->writeln("Running: {$cmd}");
        exec($cmd, $output, $code);

        foreach ($output as $line) {
            $io->writeln("  " . $line);
        }

        if ($code !== 0) {
            $io->error('Composer install failed.');
        }
    }

    private function restoreComposerJson(SymfonyStyle $io): void
    {
        $composerJson = $this->kernel->getProjectDir().'/composer.json';
        file_put_contents($composerJson, $this->composerJsonBackup);

        $io->success('composer.json restored successfully.');
    }
}
