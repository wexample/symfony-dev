<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class SetupComposerRelaxVersionsCommand extends AbstractDevCommand
{
    private array $vendorDevPaths = [];

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
        return 'Sets "*" for require and require-dev constraints of local dev packages found in vendor_dev_paths.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->vendorDevPaths)) {
            $io->error('No vendor_dev_paths configured. Please configure wexample_symfony_dev.vendor_dev_paths in your config.');

            return Command::FAILURE;
        }

        $io->section('Relaxing composer.json versions for local dev packages');

        $composerJsonPath = $this->kernel->getProjectDir().'/composer.json';
        $composerJson = file_get_contents($composerJsonPath);
        $data = json_decode($composerJson, true);

        if (! is_array($data)) {
            $io->error('Failed to read composer.json as JSON.');

            return Command::FAILURE;
        }

        $packages = $this->resolveVendorDevPackages($io);

        if (empty($packages)) {
            $io->warning('No local packages found in vendor_dev_paths.');

            return Command::SUCCESS;
        }

        $updatedApp = $this->relaxVersionsToStar($io, $data, $packages);
        $updatedLocal = $this->relaxLocalPackageDependencies($io, $packages);

        if ($updatedApp === 0 && $updatedLocal === 0) {
            $io->writeln('⊘ No matching require/require-dev entries to update in app or local packages.');

            return Command::SUCCESS;
        }

        if ($updatedApp > 0) {
            file_put_contents(
                $composerJsonPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        $io->success("Updated {$updatedApp} app composer.json constraint(s) and {$updatedLocal} local package constraint(s) to *.");

        return Command::SUCCESS;
    }

    private function resolveVendorDevPackages(SymfonyStyle $io): array
    {
        $packages = [];

        foreach ($this->vendorDevPaths as $pattern) {
            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];

            if (empty($matches)) {
                $io->writeln("⚠ No directories found for pattern: {$pattern}");

                continue;
            }

            foreach ($matches as $packagePath) {
                $composerPath = $packagePath.'/composer.json';

                if (! file_exists($composerPath)) {
                    $io->writeln("⊘ Skipping {$packagePath}: no composer.json found");

                    continue;
                }

                $composerData = json_decode(file_get_contents($composerPath), true);
                $packageName = is_array($composerData) && isset($composerData['name'])
                    ? $composerData['name']
                    : basename(dirname($packagePath)).'/'.basename($packagePath);

                $packages[$packageName] = $packagePath;
            }
        }

        return $packages;
    }

    private function relaxVersionsToStar(SymfonyStyle $io, array &$data, array $packages): int
    {
        $updated = 0;

        foreach (array_keys($packages) as $packageName) {
            if (isset($data['require'][$packageName]) && $data['require'][$packageName] !== '*') {
                $originalVersion = $data['require'][$packageName];
                $data['require'][$packageName] = '*';
                $io->writeln("  ↻ Relaxed require: {$packageName} {$originalVersion} → *");
                $updated++;
            }

            if (isset($data['require-dev'][$packageName]) && $data['require-dev'][$packageName] !== '*') {
                $originalVersion = $data['require-dev'][$packageName];
                $data['require-dev'][$packageName] = '*';
                $io->writeln("  ↻ Relaxed require-dev: {$packageName} {$originalVersion} → *");
                $updated++;
            }
        }

        return $updated;
    }

    private function relaxLocalPackageDependencies(SymfonyStyle $io, array $packages): int
    {
        $updated = 0;
        $localPackageNames = array_keys($packages);

        foreach ($packages as $packageName => $packagePath) {
            $composerPath = $packagePath.'/composer.json';
            $composerData = json_decode(file_get_contents($composerPath), true);

            if (! is_array($composerData)) {
                $io->writeln("⚠ Skipping {$composerPath}: invalid JSON");
                continue;
            }

            $changed = false;

            foreach (['require', 'require-dev'] as $section) {
                if (! isset($composerData[$section]) || ! is_array($composerData[$section])) {
                    continue;
                }

                foreach ($localPackageNames as $localName) {
                    if (isset($composerData[$section][$localName]) && $composerData[$section][$localName] !== '*') {
                        $originalVersion = $composerData[$section][$localName];
                        $composerData[$section][$localName] = '*';
                        $io->writeln("  ↻ {$packageName} {$section}: {$localName} {$originalVersion} → *");
                        $updated++;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                file_put_contents(
                    $composerPath,
                    json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        }

        return $updated;
    }
}
