<?php

namespace Wexample\SymfonyDev\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyDevExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store the vendor dev paths as a parameter for use in commands
        $container->setParameter('wexample_symfony_dev.vendor_dev_paths', $config['vendor_dev_paths']);
    }
}
