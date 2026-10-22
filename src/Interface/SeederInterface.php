<?php

namespace Wexample\SymfonyDev\Interface;

/**
 * What an application puts in its database for a demonstration or for its
 * acceptance scenarios: the content alone, where SeedService holds the
 * mechanism — the emptying, the seeding of the random source, the report.
 *
 * One class per application is the ordinary case. Several are run in the order
 * the container gives them, so anything depending on another's rows belongs in
 * the same class.
 */
interface SeederInterface
{
    public const string TAG = 'wexample_symfony_dev.seeder';

    /**
     * Writes the rows into a database just emptied. `mt_rand()` is already
     * seeded when this is called, so every draw from it is deterministic: the
     * same seed gives the same dataset, and a scenario written against it
     * keeps holding. Persisting is enough, SeedService flushes.
     *
     * @return array<string, int> what was written, by name — `['patients' => 53]` —, which `dev:seed` reports
     */
    public function load(): array;
}
