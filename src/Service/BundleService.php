<?php

namespace Wexample\SymfonyDev\Service;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class BundleService extends \Wexample\SymfonyHelpers\Service\BundleService
{
    public function versionBuild(
        BundleInterface $bundle,
        string $upgradeType = BundleHelper::UPGRADE_TYPE_MINOR,
        int $increment = 1,
        bool $build = false,
        string $version = null
    ): string {
        $config = $this->getBundleComposerConfiguration($bundle);

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

        $this->saveBundleComposerConfiguration(
            $bundle,
            $config
        );

        return $config->version;
    }
}
