<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
    protected array $devVendors;

    public function __construct(
        protected KernelInterface $kernel,
        BundleService $bundleService,
        protected ParameterBagInterface $parameterBag,
        string $name = null,
    ) {
        $devVendors = $parameterBag->has('wexample_symfony_dev.dev_vendors')
            ? (array) $parameterBag->get('wexample_symfony_dev.dev_vendors')
            : [];

        $this->devVendors = $devVendors ?: [DevHelper::DEV_COMPANY_NAME];

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
        foreach ($this->devVendors as $devVendor) {
            $localVendorPath = $this->getCompanyVendorLocalPath($devVendor);

            if (!is_dir($localVendorPath)) {
                continue;
            }

            foreach (glob($localVendorPath.'/*', GLOB_ONLYDIR) ?: [] as $localPackagePath) {
                $composerFile = PathHelper::join([
                    $localPackagePath,
                    BundleHelper::COMPOSER_JSON_FILE_NAME,
                ]);

                // Only returns valid packages.
                if (is_file($composerFile)) {
                    $callback(
                        $devVendor,
                        basename($localPackagePath),
                        $localPackagePath,
                        JsonHelper::read($composerFile)
                    );
                }
            }
        }
    }

    protected function getCompanyVendorLocalPath(string $vendorName): string
    {
        return PathHelper::join([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_LOCAL_DIR_NAME,
            $vendorName,
        ]);
    }

    protected function getCompanyVendorPath(string $vendorName): string
    {
        return PathHelper::join([
            $this->kernel->getProjectDir(),
            DevHelper::VENDOR_DIR_NAME,
            $vendorName,
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
