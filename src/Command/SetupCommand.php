<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupCommand extends AbstractDevCommand
{
    public function getDescription(): string
    {
        return 'Sets up the development environment by creating symlinks for local packages in the vendor directory.';
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        // No specific config.
        $this->execCommand(
            'cache:clear',
            $output
        );

        return Command::SUCCESS;
    }
}
