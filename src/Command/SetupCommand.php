<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class SetupCommand extends AbstractDevCommand
{
    private array $setupHooks = [];

    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        if ($parameterBag && $parameterBag->has('wexample_symfony_dev.setup_hooks')) {
            $this->setupHooks = (array) $parameterBag->get('wexample_symfony_dev.setup_hooks');
        }

        parent::__construct($kernel, $bundleService, $parameterBag, $name);
    }

    public function getDescription(): string
    {
        return 'Sets up the development environment by creating symlinks for local packages in the vendor directory.';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'skip-propagation',
            null,
            InputOption::VALUE_NONE,
            'Skip propagating relaxed versions inside local package composer.json files.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $skipPropagation = (bool) $input->getOption('skip-propagation');

        $io->title('Setting up development environment');

        $this->execCommand('dev:setup-node', $output, ['--force' => true]);
        $this->execCommand(
            'dev:setup-composer-relax-versions',
            $output,
            $skipPropagation ? [] : ['--propagate' => true]
        );
        $this->execCommand('dev:setup-composer', $output);

        if (! empty($this->setupHooks)) {
            $io->section('Running setup hooks');
            foreach ($this->setupHooks as $hook) {
                if (is_string($hook)) {
                    $this->execCommand($hook, $output);
                    continue;
                }

                if (is_array($hook) && isset($hook['command'])) {
                    $args = isset($hook['args']) && is_array($hook['args']) ? $hook['args'] : [];
                    $this->execCommand($hook['command'], $output, $args);
                    continue;
                }

                $io->warning('Invalid setup hook definition. Expected string or {command, args}.');
            }
        }

        $io->section('Clearing Symfony cache');
        $this->execCommand('cache:clear', $output);

        $io->success('Development environment setup completed successfully.');

        return Command::SUCCESS;
    }
}
