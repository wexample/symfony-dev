<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BundleUpdateAll extends AbstractDevCommand
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $this->bundleService->updateAllLocalPackages();

        foreach ($this->bundleService->updateAllLocalPackages() as $path => $version) {
            $io->success('Updated package '.$path.' to '.$version);
        }

        $this->execCommand(
            BundleUpdateRequirements::class,
            $output
        );

        $this->execCommand(
            ImportVersionsCommand::class,
            $output
        );

        return Command::SUCCESS;
    }
}
