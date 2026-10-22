<?php

namespace Wexample\SymfonyDev\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Wexample\SymfonyDev\Interface\SeederInterface;

/**
 * The mechanism behind `dev:seed` and the development menu's entry: the
 * database emptied, the random source seeded, then the rows written by the
 * application's seeders. The same seed gives the same dataset, so acceptance
 * scenarios can name the rows they walk through.
 *
 * The content is never here — an application declares it in a SeederInterface.
 */
class SeedService
{
    /**
     * Any fixed number does; this one is written in every scenario document
     * that relies on the dataset.
     */
    public const int DEFAULT_SEED = 2026;

    /**
     * @param iterable<SeederInterface> $seeders
     */
    public function __construct(
        private readonly DatabaseResetService $databaseReset,
        private readonly EntityManagerInterface $entityManager,
        #[AutowireIterator(SeederInterface::TAG)]
        private readonly iterable $seeders,
    ) {
    }

    /**
     * Whether the application declared any data to load: what the command and
     * the menu ask before offering to reload nothing.
     */
    public function hasSeeders(): bool
    {
        foreach ($this->seeders as $seeder) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, int> what was written, by name, the seeders' reports merged
     */
    public function seed(int $seed = self::DEFAULT_SEED): array
    {
        mt_srand($seed);

        $this->databaseReset->truncateAll();

        $counts = [];

        foreach ($this->seeders as $seeder) {
            foreach ($seeder->load() as $name => $count) {
                $counts[$name] = ($counts[$name] ?? 0) + $count;
            }
        }

        $this->entityManager->flush();

        return $counts;
    }
}
