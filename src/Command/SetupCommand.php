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

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        
        $io->section('Setting up node_modules symlinks for dev packages');
        
        $nodeModulesPath = $this->kernel->getProjectDir().'/node_modules';
        
        if (!is_dir($nodeModulesPath)) {
            $io->warning('node_modules directory not found at: '.$nodeModulesPath);
            $io->note('Run yarn install first');
        } else {
            $symlinkCount = 0;
            
            foreach ($this->vendorDevPaths as $pattern) {
                $paths = glob($pattern, GLOB_ONLYDIR) ?: [];
                
                foreach ($paths as $packagePath) {
                    // Only create symlink for packages with assets/package.json
                    $packageJsonPath = $packagePath.'/assets/package.json';
                    if (!is_file($packageJsonPath)) {
                        continue;
                    }
                    
                    $symlinkPath = $packagePath.'/node_modules';
                    
                    // Remove existing symlink or directory if it exists
                    if (is_link($symlinkPath)) {
                        unlink($symlinkPath);
                    } elseif (is_dir($symlinkPath)) {
                        $io->warning("Skipping {$packagePath}: node_modules is a real directory, not a symlink");
                        continue;
                    }
                    
                    // Create symlink
                    if (symlink($nodeModulesPath, $symlinkPath)) {
                        $io->writeln("✓ Created symlink: {$symlinkPath} -> {$nodeModulesPath}");
                        $symlinkCount++;
                    } else {
                        $io->error("Failed to create symlink: {$symlinkPath}");
                    }
                }
            }
            
            $io->success("Created {$symlinkCount} node_modules symlink(s)");
        }
        
        $io->section('Clearing cache');
        
        $this->execCommand(
            'cache:clear',
            $output
        );

        return Command::SUCCESS;
    }
}
