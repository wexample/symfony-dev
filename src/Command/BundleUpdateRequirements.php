<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BundleUpdateRequirements extends AbstractDevCommand
{
    public function getDescription(): string
    {
        return "Updates dependencies versions";
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $updated = $this->bundleService->updateAllRequirementsVersions();

        foreach ($updated as $name => $path) {
            $io->success('Updated package '.$name);
        }

        return Command::SUCCESS;
    }
}
