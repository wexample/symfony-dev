<?php

namespace Wexample\SymfonyDev\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class ChangeAllUsersPasswordCommand extends AbstractDevCommand
{
    private const OPTION_CLASS = 'class';
    private const OPTION_PASSWORD = 'password';

    public function __construct(
        KernelInterface $kernel,
        BundleService $bundleService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ManagerRegistry $managerRegistry,
        ?ParameterBagInterface $parameterBag = null,
        string $name = null,
    ) {
        parent::__construct($kernel, $bundleService, $parameterBag, $name);
    }

    public function getDescription(): string
    {
        return 'Changes the password for all users (dev helper).';
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                self::OPTION_CLASS,
                'c',
                InputOption::VALUE_REQUIRED,
                'User entity class.',
                'App\\Entity\\User'
            )
            ->addOption(
                self::OPTION_PASSWORD,
                'p',
                InputOption::VALUE_REQUIRED,
                'New plain password for all users (will be hashed).',
                'password'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $className = (string) $input->getOption(self::OPTION_CLASS);
        $password = (string) $input->getOption(self::OPTION_PASSWORD);

        $entityManager = $this->managerRegistry->getManagerForClass($className);
        if (! $entityManager) {
            $io->error(sprintf('No entity manager found for "%s".', $className));

            return Command::FAILURE;
        }

        $repository = $entityManager->getRepository($className);
        $users = $repository->findAll();

        if (empty($users)) {
            $io->warning('No users found.');

            return Command::SUCCESS;
        }

        $updatedCount = 0;

        foreach ($users as $user) {
            if (! method_exists($user, 'setPassword')) {
                $io->error(sprintf('User class "%s" does not have a setPassword() method.', $className));

                return Command::FAILURE;
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $entityManager->persist($user);
            $updatedCount++;
        }

        $entityManager->flush();

        $io->success(sprintf('Password updated for %d user(s).', $updatedCount));

        return Command::SUCCESS;
    }
}
