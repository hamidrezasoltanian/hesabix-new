<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extended template fields to print_template';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_template
            ADD name VARCHAR(100) DEFAULT NULL,
            ADD invoice_template LONGTEXT DEFAULT NULL,
            ADD preinvoice_template LONGTEXT DEFAULT NULL,
            ADD storeroom_template LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE print_template
            DROP COLUMN name,
            DROP COLUMN invoice_template,
            DROP COLUMN preinvoice_template,
            DROP COLUMN storeroom_template');
    }
}
