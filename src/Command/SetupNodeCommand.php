<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyDev\Service\JsDevPackagesResolver;

class SetupNodeCommand extends AbstractDevCommand
{
    private array $vendorDevPaths = [];

    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        private readonly JsDevPackagesResolver $jsDevPackagesResolver,
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
        return 'Sets up node_modules symlinks for local dev packages.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Setting up node_modules symlinks for dev packages');
        $this->setupNodeModulesSymlinks($io);

        $io->section('Installing node_modules for JS dev packages');
        $this->installJsDevPackagesNodeModules($io);

        return Command::SUCCESS;
    }

    private function setupNodeModulesSymlinks(SymfonyStyle $io): void
    {
        $nodeModulesPath = $this->kernel->getProjectDir().'/node_modules';

        if (! is_dir($nodeModulesPath)) {
            $io->warning('node_modules directory not found. Run yarn install first.');

            return;
        }

        $symlinkCount = 0;

        foreach ($this->vendorDevPaths as $pattern) {
            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $packagePath) {
                $packageJsonPath = $packagePath.'/assets/package.json';
                if (! is_file($packageJsonPath)) {
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

    private function installJsDevPackagesNodeModules(SymfonyStyle $io): void
    {
        $packages = $this->jsDevPackagesResolver->resolvePackages();

        if (empty($packages)) {
            $io->writeln('⊘ No js_dev_packages configured or found.');

            return;
        }

        foreach ($packages as $alias => $path) {
            $nodeModulesPath = $path.'/node_modules';

            if (is_dir($nodeModulesPath)) {
                $io->writeln("⊘ {$alias}: node_modules already exists");
                continue;
            }

            $io->writeln("→ Installing {$alias} ({$path})");
            $exitCode = $this->runYarnInstall($path);

            if ($exitCode === 0) {
                $io->writeln("✓ Installed {$alias}");
            } else {
                $io->warning("Failed to install {$alias} (exit code: {$exitCode})");
            }
        }
    }

    private function runYarnInstall(string $workingDir): int
    {
        $previousDir = getcwd();

        if ($previousDir === false) {
            return 1;
        }

        chdir($workingDir);
        passthru('yarn install', $exitCode);
        chdir($previousDir);

        return (int) $exitCode;
    }
}
