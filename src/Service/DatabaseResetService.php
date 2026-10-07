<?php

namespace Wexample\SymfonyDev\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Empties every table of the application's database, the migrations' own left
 * alone — what a seeder does before writing its rows, and what a test does
 * between two scenarios. It belongs to this bundle, which production does not
 * load: nothing here asks twice.
 */
class DatabaseResetService
{
    /**
     * Doctrine's own table: emptying it would have the application believe no
     * migration ever ran.
     */
    public const string MIGRATIONS_TABLE = 'doctrine_migration_versions';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<string> $keep tables left as they are, beside the migrations' own
     */
    public function truncateAll(array $keep = []): void
    {
        $tables = array_diff(
            $this->connection->createSchemaManager()->listTableNames(),
            [self::MIGRATIONS_TABLE, ...$keep],
        );

        if (! $tables) {
            return;
        }

        $quoted = array_map(
            fn (string $table): string => $this->connection->quoteSingleIdentifier($table),
            array_values($tables),
        );
        $platform = $this->connection->getDatabasePlatform();

        // One statement where the platform cascades the foreign keys itself;
        // the keys lifted around the statements where it does not, since the
        // order the tables come in is nobody's choice.
        if ($platform instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('TRUNCATE '.implode(', ', $quoted).' CASCADE');

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($quoted as $table) {
                $this->connection->executeStatement('TRUNCATE TABLE '.$table);
            }

            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

            return;
        }

        // SQLite and the rest: no TRUNCATE, a delete per table.
        foreach ($quoted as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}
