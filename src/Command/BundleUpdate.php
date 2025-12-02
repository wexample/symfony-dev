<?php

namespace Wexample\SymfonyDev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyDev\Command\Traits\WithArgPackage;
use Wexample\SymfonyHelpers\Helper\BundleHelper;

class BundleUpdate extends AbstractDevCommand
{
    use WithArgPackage;

    public function getDescription(): string
    {
        return "Updates one single package";
    }

    protected function configure(): void
    {
        $this->configurePackageArg(
            InputArgument::REQUIRED
        );

        $this->addOption(
            'upgradeType',
            't',
            InputOption::VALUE_OPTIONAL,
            'Upgrade type',
            BundleHelper::UPGRADE_TYPE_MINOR
        )
            ->addOption(
                'increment',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Increment value',
                1
            )
            ->addOption(
                'build',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Build',
                false
            )
            ->addOption(
                'version-number',
                'vn',
                InputOption::VALUE_OPTIONAL,
                'Version number'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $bundleName = $this->getPackageArg($input);
        if (! $bundle = BundleHelper::getBundle(
            BundleHelper::buildClassNameFromPackageName($bundleName),
            $this->kernel
        )) {
            $io->error('Bundle not found '.$bundleName);

            return Command::FAILURE;
        }

        $upgradeType = $input->getOption('upgradeType');

        if (! in_array($upgradeType, BundleHelper::UPGRADE_TYPES)) {
            $io->error('Unsupported release type '.$upgradeType);

            return Command::FAILURE;
        }

        $increment = $input->getOption('increment');
        $build = $input->getOption('build');
        $version = $input->getOption('version-number');

        if ($newVersion = $this->bundleService->versionBuild(
            BundleHelper::getBundleRootPath($bundle, $this->kernel),
            $upgradeType,
            $increment,
            $build,
            $version,
        )) {
            $io->success('New version for '.$bundleName.' : '.$newVersion);
        }

        $this->execCommand(
            BundleUpdateRequirements::class,
            $output
        );

        $this->execCommand(
            ImportVersionsCommand::class,
            $output
        );

        return Command::SUCCESS;
    }
}
