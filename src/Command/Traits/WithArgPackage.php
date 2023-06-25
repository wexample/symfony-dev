<?php

namespace Wexample\SymfonyDev\Command\Traits;

use Symfony\Component\Console\Input\InputInterface;

trait WithArgPackage
{
    const ARGUMENT_NAME_PACKAGE_NAME = 'packageName';

    protected function configurePackageArg(int $mode = null): void
    {
        $this
            ->addArgument(
                self::ARGUMENT_NAME_PACKAGE_NAME,
                $mode,
                'Package name'
            );
    }

    protected function getPackageArg(InputInterface $input): string
    {
        return $input->getArgument(
            self::ARGUMENT_NAME_PACKAGE_NAME
        );
    }
}
