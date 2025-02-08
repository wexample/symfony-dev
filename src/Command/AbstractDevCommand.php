<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\Helpers\Helper\PathHelper;
use Wexample\SymfonyDev\Helper\DevHelper;
use Wexample\SymfonyDev\WexampleSymfonyDevBundle;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\JsonHelper;
use Wexample\SymfonyHelpers\Service\BundleService;

abstract class AbstractDevCommand extends AbstractBundleCommand
{
    protected BundleService $bundleService;

    public function __construct(
        protected KernelInterface $kernel,
        BundleService $bundleService,
        string $name = null,
    ) {
        parent::__construct(
            $bundleService,
            $name
        );
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyDevBundle::class;
    }

    protected function forEachDevPackage(callable $callback): void
    {
        $localVendorPath = $this->getCompanyVendorLocalPath();

        foreach (glob($localVendorPath.'/*', GLOB_ONLYDIR) as $localPackagePath) {
            $composerFile = PathHelper::join([
                $localPackagePath,
                BundleHelper::COMPOSER_JSON_FILE_NAME,
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
        return PathHelper::join([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_LOCAL_DIR_NAME,
            DevHelper::DEV_COMPANY_NAME,
        ]);
    }

    protected function getCompanyVendorPath(): string
    {
        return PathHelper::join([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_DIR_NAME,
            DevHelper::DEV_COMPANY_NAME,
        ]);
    }

    protected function writeAppComposerConfig(
        $config,
        bool $removeLockFile = false
    ): void {
        JsonHelper::write(
            $this->getAppComposerConfigPath(),
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($removeLockFile) {
            FileHelper::deleteFileIfExists(
                $this->kernel->getProjectDir().'/composer.lock'
            );
        }
    }

    protected function getAppComposerConfigPath(): string
    {
        return PathHelper::join([
            $this->kernel->getProjectDir(),
            BundleHelper::COMPOSER_JSON_FILE_NAME,
        ]);
    }

    protected function getAppComposerConfig(int $flags = null): array|object
    {
        return JsonHelper::read(
            $this->getAppComposerConfigPath(),
            $flags
        );
    }
}
