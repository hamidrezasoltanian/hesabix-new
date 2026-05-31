<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminder_sent column to cheque table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cheque ADD reminder_sent TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cheque DROP COLUMN reminder_sent');
    }
}
