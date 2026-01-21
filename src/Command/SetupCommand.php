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
    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        parent::__construct($kernel, $bundleService, $parameterBag, $name);
    }

    public function getDescription(): string
    {
        return 'Sets up the development environment by creating symlinks for local packages in the vendor directory.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Setting up development environment');

        $this->execCommand('dev:setup-node', $output, ['--force' => true]);
        $this->execCommand('dev:setup-composer', $output);

        $io->section('Clearing Symfony cache');
        $this->execCommand('cache:clear', $output);

        $io->success('Development environment setup completed successfully.');

        return Command::SUCCESS;
    }
}
