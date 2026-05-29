<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lot/expiry to storeroom_item; add person_followup table';
    }

    public function up(Schema $schema): void
    {
        // WMS: lot number and expiry date on storeroom items
        $this->addSql('ALTER TABLE storeroom_item ADD lot_no VARCHAR(255) DEFAULT NULL, ADD expiry_date VARCHAR(50) DEFAULT NULL');

        // CRM: person followup / collections tracking
        $this->addSql('CREATE TABLE person_followup (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            person_id INT NOT NULL,
            submitter_id INT NOT NULL,
            year_id INT NOT NULL,
            date VARCHAR(50) NOT NULL,
            followup_date VARCHAR(50) DEFAULT NULL,
            type VARCHAR(50) NOT NULL,
            des LONGTEXT DEFAULT NULL,
            amount NUMERIC(30, 0) DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            INDEX IDX_person_followup_bid (bid_id),
            INDEX IDX_person_followup_person (person_id),
            INDEX IDX_person_followup_year (year_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE person_followup
            ADD CONSTRAINT FK_person_followup_bid FOREIGN KEY (bid_id) REFERENCES business (id),
            ADD CONSTRAINT FK_person_followup_person FOREIGN KEY (person_id) REFERENCES person (id),
            ADD CONSTRAINT FK_person_followup_submitter FOREIGN KEY (submitter_id) REFERENCES user (id),
            ADD CONSTRAINT FK_person_followup_year FOREIGN KEY (year_id) REFERENCES year (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE storeroom_item DROP COLUMN lot_no, DROP COLUMN expiry_date');
        $this->addSql('ALTER TABLE person_followup DROP FOREIGN KEY FK_person_followup_bid');
        $this->addSql('ALTER TABLE person_followup DROP FOREIGN KEY FK_person_followup_person');
        $this->addSql('ALTER TABLE person_followup DROP FOREIGN KEY FK_person_followup_submitter');
        $this->addSql('ALTER TABLE person_followup DROP FOREIGN KEY FK_person_followup_year');
        $this->addSql('DROP TABLE person_followup');
    }
}
