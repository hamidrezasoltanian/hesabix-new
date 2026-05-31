<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed missing system registry keys so registryMGR::get() never needs to INSERT on first request';
    }

    public function up(Schema $schema): void
    {
        // INSERT IGNORE is idempotent — safe to re-run.
        // These keys are read by UiGeneralController on every page load;
        // if they are missing registryMGR tries to INSERT, which races and fails.
        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('system', 'appName',   'Hesabix'),
            ('system', 'appSlogan', ''),
            ('system', 'appUrl',    '')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM registry WHERE root = 'system' AND name IN ('appName','appSlogan','appUrl')");
    }
}
