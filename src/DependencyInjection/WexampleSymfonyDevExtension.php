<?php

namespace Wexample\SymfonyDev\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyDesignSystem\Interface\DevMenuProviderInterface;
use Wexample\SymfonyDev\DevMenu\SeedDevMenuProvider;
use Wexample\SymfonyDev\Interface\SeederInterface;
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
        $container->setParameter('wexample_symfony_dev.js_dev_packages', $config['js_dev_packages'] ?? []);
        $container->setParameter('wexample_symfony_dev.setup_hooks', $config['setup_hooks'] ?? []);

        // What an application declares as its demonstration data, found by
        // SeedService without the application wiring anything.
        $container
            ->registerForAutoconfiguration(SeederInterface::class)
            ->addTag(SeederInterface::TAG);

        // The entry reloading it, only where the development menu exists.
        if (interface_exists(DevMenuProviderInterface::class)) {
            $container
                ->register(SeedDevMenuProvider::class, SeedDevMenuProvider::class)
                ->setAutowired(true)
                ->addTag(DevMenuProviderInterface::TAG);
        }
    }
}
