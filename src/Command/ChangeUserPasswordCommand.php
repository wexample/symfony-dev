<?php

namespace Wexample\SymfonyDev\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Wexample\SymfonyHelpers\Service\BundleService;

class ChangeUserPasswordCommand extends AbstractDevCommand
{
    private const ARG_IDENTIFIER = 'identifier';
    private const OPTION_CLASS = 'class';
    private const OPTION_FIELD = 'field';
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
        return 'Changes a user password by identifier.';
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                self::ARG_IDENTIFIER,
                InputArgument::REQUIRED,
                'User identifier value (email/username/id).'
            )
            ->addOption(
                self::OPTION_CLASS,
                'c',
                InputOption::VALUE_REQUIRED,
                'User entity class.',
                'App\\Entity\\User'
            )
            ->addOption(
                self::OPTION_FIELD,
                'f',
                InputOption::VALUE_REQUIRED,
                'Field used to find the user.',
                'email'
            )
            ->addOption(
                self::OPTION_PASSWORD,
                'p',
                InputOption::VALUE_REQUIRED,
                'New plain password (will be hashed).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = (string) $input->getArgument(self::ARG_IDENTIFIER);
        $className = (string) $input->getOption(self::OPTION_CLASS);
        $fieldName = (string) $input->getOption(self::OPTION_FIELD);
        $password = $input->getOption(self::OPTION_PASSWORD);

        if (! $password && $input->isInteractive()) {
            $password = $io->askHidden('New password');
        }

        if (! $password) {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        $entityManager = $this->managerRegistry->getManagerForClass($className);
        if (! $entityManager) {
            $io->error(sprintf('No entity manager found for "%s".', $className));

            return Command::FAILURE;
        }

        $repository = $entityManager->getRepository($className);
        $user = $repository->findOneBy([$fieldName => $identifier]);

        if (! $user) {
            $io->error(sprintf('No user found for %s="%s".', $fieldName, $identifier));

            return Command::FAILURE;
        }

        if (! method_exists($user, 'setPassword')) {
            $io->error(sprintf('User class "%s" does not have a setPassword() method.', $className));

            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $entityManager->persist($user);
        $entityManager->flush();

        $io->success('Password updated successfully.');

        return Command::SUCCESS;
    }
}
