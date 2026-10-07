<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyDev\Interface\SeederInterface;
use Wexample\SymfonyDev\Service\SeedService;
use Wexample\SymfonyDev\Traits\SymfonyDevBundleClassTrait;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;

/**
 * `dev:seed`: empties the database and loads the application's demonstration
 * data again (SeederInterface). The same `--seed` gives the same rows.
 */
class SeedCommand extends AbstractBundleCommand
{
    use SymfonyDevBundleClassTrait;

    protected static $defaultDescription = 'Empties the database and loads the demonstration data again';

    private const string OPTION_FORCE = 'force';

    private const string OPTION_SEED = 'seed';

    public function __construct(
        BundleService $bundleService,
        private readonly SeedService $seedService,
        ?string $name = null,
    ) {
        parent::__construct($bundleService, $name);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Confirms the database may be emptied')
            ->addOption(self::OPTION_SEED, null, InputOption::VALUE_REQUIRED, 'Random seed', (string) SeedService::DEFAULT_SEED);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        if (! $this->seedService->hasSeeders()) {
            $io->error('No data to load: this application declares no '.SeederInterface::class.'.');

            return self::FAILURE;
        }

        if (! $input->getOption(self::OPTION_FORCE)) {
            $io->error('This empties the whole database: run it again with --force.');

            return self::FAILURE;
        }

        $counts = $this->seedService->seed((int) $input->getOption(self::OPTION_SEED));

        $io->success('Loaded.');
        $io->listing(array_map(
            static fn (string $name, int $count): string => $count.' '.$name,
            array_keys($counts),
            $counts,
        ));

        return self::SUCCESS;
    }
}
