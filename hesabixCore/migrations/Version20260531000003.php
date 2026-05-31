<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AUTO_INCREMENT to all id columns that are missing it';
    }

    // DDL (ALTER TABLE) in MySQL causes an implicit commit, so Doctrine cannot
    // roll back on failure. Returning false suppresses the "already rolled back"
    // deprecation warning and matches MySQL's actual behaviour.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Disable FK checks so that MySQL lets us MODIFY columns that are
        // referenced by foreign-key constraints in other tables.
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        // Find every int `id` column that is missing AUTO_INCREMENT.
        // Doctrine's #[ORM\GeneratedValue] expects AUTO_INCREMENT; without it
        // every INSERT fails with "Field id doesn't have a default value".
        $db = $this->connection->getDatabase();

        $rows = $this->connection->executeQuery(
            "SELECT TABLE_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND COLUMN_NAME  = 'id'
               AND DATA_TYPE    = 'int'
               AND EXTRA NOT LIKE '%auto_increment%'
             ORDER BY TABLE_NAME",
            ['db' => $db]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $table = $row['TABLE_NAME'];
            $this->addSql("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
        }

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        // AUTO_INCREMENT cannot be safely removed without knowing which tables
        // genuinely needed it added, so down() is intentionally left empty.
    }
}
