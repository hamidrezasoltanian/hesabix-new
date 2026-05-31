<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns missing from permission table: plug_accpro_presell, plug_hrm_docs, plug_ghesta_manager';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $db = $this->connection->getDatabase();

        $existing = $this->connection->executeQuery(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'permission'",
            ['db' => $db]
        )->fetchFirstColumn();

        if (!in_array('plug_accpro_presell', $existing, true)) {
            $this->addSql('ALTER TABLE permission ADD plug_accpro_presell TINYINT(1) DEFAULT NULL');
        }
        if (!in_array('plug_hrm_docs', $existing, true)) {
            $this->addSql('ALTER TABLE permission ADD plug_hrm_docs TINYINT(1) DEFAULT NULL');
        }
        if (!in_array('plug_ghesta_manager', $existing, true)) {
            $this->addSql('ALTER TABLE permission ADD plug_ghesta_manager TINYINT(1) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission DROP COLUMN plug_accpro_presell');
        $this->addSql('ALTER TABLE permission DROP COLUMN plug_hrm_docs');
        $this->addSql('ALTER TABLE permission DROP COLUMN plug_ghesta_manager');
    }
}
