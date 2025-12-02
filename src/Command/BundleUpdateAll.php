<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BundleUpdateAll extends AbstractDevCommand
{
    public function getDescription(): string
    {
        return "Updates all packages and dependencies versions";
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->bundleService->updateAllLocalPackages() as $path => $version) {
            $io->success('Updated package '.$path.' to '.$version);
        }

        $this->execCommand(
            BundleUpdateRequirements::class,
            $output
        );

        return Command::SUCCESS;
    }
}
