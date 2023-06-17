<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyDev\Helper\DevHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\JsonHelper;

abstract class AbstractDevCommand extends Command
{
    public function __construct(
        protected readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function forEachDevPackage(callable $callback): void
    {
        $localVendorPath = $this->getCompanyVendorLocalPath();

        foreach (glob($localVendorPath.'/*', GLOB_ONLYDIR) as $localPackagePath) {
            $composerFile = FileHelper::joinPathParts([
                $localPackagePath,
                DevHelper::COMPOSER_JSON_FILE_NAME,
            ]);

            // Only returns valid packages.
            if (is_file($composerFile)) {
                $callback(
                    basename($localPackagePath),
                    JsonHelper::read($composerFile)
                );
            }
        }
    }

    protected function getCompanyVendorLocalPath(): string
    {
        return FileHelper::joinPathParts([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_LOCAL_DIR_NAME,
            DevHelper::DEV_COMPANY_NAME,
        ]);
    }

    protected function getCompanyVendorPath(): string
    {
        return FileHelper::joinPathParts([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_DIR_NAME,
            DevHelper::DEV_COMPANY_NAME,
        ]);
    }
}
