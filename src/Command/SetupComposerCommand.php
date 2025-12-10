<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class SetupComposerCommand extends AbstractDevCommand
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
        return 'Sets up local Composer path repositories for development.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Configuring local Composer path repositories (temporary)');
        $this->applyLocalComposerRepositories($io);

        $io->section('Running Composer install using local repositories');
        $this->runComposerInstallWithLocalRepos($io);

        $io->section('Restoring original composer.json');
        $this->restoreComposerJson($io);

        return Command::SUCCESS;
    }

    private function applyLocalComposerRepositories(SymfonyStyle $io): void
    {
        $composerJson = $this->kernel->getProjectDir().'/composer.json';
        $this->composerJsonBackup = file_get_contents($composerJson);

        $data = json_decode($this->composerJsonBackup, true);

        if (!isset($data['repositories'])) {
            $data['repositories'] = [];
        }

        # TODO Bad version

//        foreach ($this->vendorDevPaths as $pattern) {
//
//            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];
//
//            if (empty($matches)) {
//                $io->warning("No match for pattern: {$pattern}");
//                continue;
//            }
//
//            foreach ($matches as $packagePath) {
//                $repo = [
//                    'type'    => 'path',
//                    'url'     => $packagePath,
//                    'options' => ['symlink' => true],
//                ];
//
//                $data['repositories'][] = $repo;
//
//                // Diagnostic 1 : vérifier que le package existe dans vendor
//                $vendorName = basename(dirname($packagePath));
//                $packageName = basename($packagePath);
//                $vendorDir = $this->kernel->getProjectDir()."/vendor/{$vendorName}/{$packageName}";
//
//                if (!is_dir($vendorDir)) {
//                    $io->warning("Package not installed in vendor: {$vendorName}/{$packageName}");
//                } else {
//                    $io->writeln("→ Repo added for installed package: {$vendorName}/{$packageName}");
//                }
//            }
//        }

        file_put_contents($composerJson, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function runComposerInstallWithLocalRepos(SymfonyStyle $io): void
    {
        $io->note([
            'Running composer install using the TEMPORARY composer.json.',
            'Composer may display a warning saying that composer.lock does not match the modified composer.json.',
            'This warning is expected because we temporarily inject local "path" repositories.',
            'Composer install ALWAYS respects composer.lock.',
            'Therefore, the lock file is NOT modified and the installation remains 100% production-safe.',
            'After this step, composer.json will be restored to its original state.',
        ]);

        $cmd = 'composer install --no-interaction';
        $io->writeln("Running: {$cmd}");
        exec($cmd, $output, $code);

        foreach ($output as $line) {
            $io->writeln("  " . $line);
        }

        if ($code !== 0) {
            $io->error('Composer install failed.');
        } else {
            $io->success('Composer install (dev mode) completed successfully.');
        }
    }

    private function restoreComposerJson(SymfonyStyle $io): void
    {
        $composerJson = $this->kernel->getProjectDir().'/composer.json';
        file_put_contents($composerJson, $this->composerJsonBackup);

        $io->success('composer.json restored successfully.');
    }
}
