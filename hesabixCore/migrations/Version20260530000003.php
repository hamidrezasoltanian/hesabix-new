<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add province_manager table and responsible_id to person';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE province_manager (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            province VARCHAR(100) NOT NULL,
            INDEX IDX_PROV_MGR_BID (bid_id),
            INDEX IDX_PROV_MGR_USER (user_id),
            UNIQUE INDEX uniq_prov_mgr (bid_id, province),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE province_manager
            ADD CONSTRAINT FK_province_manager_bid FOREIGN KEY (bid_id) REFERENCES business (id),
            ADD CONSTRAINT FK_province_manager_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE person ADD responsible_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_person_responsible FOREIGN KEY (responsible_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_person_responsible ON person (responsible_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_person_responsible');
        $this->addSql('ALTER TABLE person DROP INDEX IDX_person_responsible');
        $this->addSql('ALTER TABLE person DROP COLUMN responsible_id');
        $this->addSql('ALTER TABLE province_manager DROP FOREIGN KEY FK_province_manager_bid');
        $this->addSql('ALTER TABLE province_manager DROP FOREIGN KEY FK_province_manager_user');
        $this->addSql('DROP TABLE province_manager');
    }
}
