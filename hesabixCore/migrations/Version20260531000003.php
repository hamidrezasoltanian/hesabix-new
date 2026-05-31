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

    public function up(Schema $schema): void
    {
        // Doctrine's #[ORM\GeneratedValue] expects AUTO_INCREMENT on all id columns.
        // If the database was bootstrapped without it (e.g. schema:create failure or partial
        // dump import), every INSERT will fail with "Field id doesn't have a default value".
        // This migration finds every int id column that lacks AUTO_INCREMENT and adds it.

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
            // MODIFY is idempotent if AUTO_INCREMENT is already present.
            $this->addSql("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
        }
    }

    public function down(Schema $schema): void
    {
        // AUTO_INCREMENT cannot be safely removed without knowing which tables
        // genuinely needed it, so down() is intentionally left empty.
    }
}
