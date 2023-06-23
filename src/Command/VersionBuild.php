<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class VersionBuild extends AbstractDevCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'bundleName',
                InputArgument::REQUIRED,
                'Bundle name');

        $this->addOption(
            'upgradeType',
            't',
            InputOption::VALUE_OPTIONAL,
            'Upgrade type',
            BundleHelper::UPGRADE_TYPE_MINOR)
            ->addOption(
                'increment',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Increment value',
                1)
            ->addOption(
                'build',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Build',
                false)
            ->addOption(
                'version-number',
                'vn',
                InputOption::VALUE_OPTIONAL,
                'Version number');

    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $bundleName = $input->getArgument('bundleName');
        if (!$bundle = $this->bundleService->getBundleIfExists(
            BundleHelper::buildClassNameFromPackageName($bundleName)
        )) {
            $io->error('Bundle not found '.$bundleName);

            return Command::FAILURE;
        }

        $upgradeType = $input->getOption('upgradeType');

        if (!in_array($upgradeType, BundleHelper::UPGRADE_TYPES)) {
            $io->error('Unsupported release type '.$upgradeType);
            return Command::FAILURE;
        }

        $increment = $input->getOption('increment');
        $build = $input->getOption('build');
        $version = $input->getOption('version-number');

        if ($newVersion = $this->bundleService->versionBuild(
            $bundle,
            $upgradeType,
            $increment,
            $build,
            $version,
        )) {
            $io->success('New version for '.$bundleName.' : '.$newVersion);
        }

        return Command::SUCCESS;
    }
}
