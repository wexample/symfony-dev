<?php

namespace Wexample\SymfonyDev\Service;

use Wexample\SymfonyHelpers\Helper\BundleHelper;

class BundleService extends \Wexample\SymfonyHelpers\Service\BundleService
{
    /**
     * Increment every package and update dependencies.
     * @return array
     */
    public function updateAllLocalPackages(): array
    {
        $paths = $this->getAllLocalPackagesPaths();
        $output = [];

        foreach ($paths as $path) {
            if ($newVersion = $this->versionBuild($path)) {
                $output[$this->getPackageComposerConfiguration($path)->name] = $newVersion;
            }
        }

        $this->updateAllRequirementsVersions();

        return $output;
    }

    public function versionBuild(
        string $packagePath,
        string $upgradeType = BundleHelper::UPGRADE_TYPE_MINOR,
        int $increment = 1,
        bool $build = false,
        string $version = null
    ): string {
        $config = $this->getPackageComposerConfiguration($packagePath);

        if (!$version) {
            $version = $config->version;
        }

        // Version increment
        $config->version = BundleHelper::defaultVersionIncrement(
            $version,
            $upgradeType,
            $increment,
            $build
        );

        $this->savePackageComposerConfiguration(
            $packagePath,
            $config
        );

        return $config->version;
    }

    public function updateAllRequirementsVersions(): array
    {
        $packages = $this->getAllLocalPackagesPaths();
        $updated = [];

        foreach ($packages as $packagePath) {
            $updated += $this->updateRequirementVersion(
                $packagePath,
            );
        }

        return $updated;
    }

    public function getAllLocalPackagesPaths(): array
    {
        $vendorsDir = $this->kernel->getProjectDir().'/vendor-local/';
        $vendors = scandir($vendorsDir);

        $packages = [];
        foreach ($vendors as $vendor) {
            if ($vendor[0] !== '.') {
                $vendorDir = $vendorsDir.$vendor.'/';
                foreach (scandir($vendorDir) as $package) {
                    if ($package[0] !== '.') {
                        $packages[$vendor.'/'.$package] = $vendorDir.$package.'/';
                    }
                }
            }
        }

        return $packages;
    }

    public function updateRequirementVersion(string $packagePath): array
    {
        $packages = $this->getAllLocalPackagesPaths();
        $config = $this->getPackageComposerConfiguration($packagePath);
        $packageName = $config->name;
        $updated = [];

        foreach ($packages as $packageNameDest => $packageDestPath) {
            if ($packageNameDest !== $packageName) {
                $configDest = $this->getPackageComposerConfiguration($packageDestPath);
                $changed = false;
                $newVersion = '^'.$config->version;

                if (isset($configDest->require->$packageName)
                    && $configDest->require->$packageName != $newVersion) {
                    $changed = true;
                    $configDest->require->$packageName = $newVersion;
                }

                $requireDevKey = 'require-dev';
                if (isset($configDest->$requireDevKey->$packageName)
                    && $configDest->$requireDevKey->$packageName != $newVersion) {
                    $changed = true;
                    $configDest->require->$packageName = $newVersion;
                }

                if ($changed) {
                    $this->savePackageComposerConfiguration(
                        $packageDestPath,
                        $configDest
                    );

                    $updated[$configDest->name] = $packageDestPath;
                }
            }
        }

        return $updated;
    }
}
