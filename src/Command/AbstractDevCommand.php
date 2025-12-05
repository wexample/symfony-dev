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
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        $devVendors = [];

        if ($parameterBag && $parameterBag->has('wexample_symfony_dev.dev_vendors')) {
            $devVendors = (array) $parameterBag->get('wexample_symfony_dev.dev_vendors');
        }

        $this->devVendors = $devVendors ?: [];

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
            $vendorPath = $this->getCompanyVendorPath($devVendor);

            if (!is_dir($vendorPath)) {
                continue;
            }

            foreach (glob($vendorPath.'/*', GLOB_ONLYDIR) ?: [] as $packagePath) {
                $composerFile = PathHelper::join([
                    $packagePath,
                    BundleHelper::COMPOSER_JSON_FILE_NAME,
                ]);

                // Only returns valid packages.
                if (is_file($composerFile)) {
                    $callback(
                        $devVendor,
                        basename($packagePath),
                        $packagePath,
                        JsonHelper::read($composerFile)
                    );
                }
            }
        }
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
