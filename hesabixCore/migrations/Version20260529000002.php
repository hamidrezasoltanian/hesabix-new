<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sales_center and week_plan tables for weekly sales planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sales_center (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            owner_id INT DEFAULT NULL,
            person_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            province VARCHAR(255) DEFAULT NULL,
            city VARCHAR(255) DEFAULT NULL,
            type VARCHAR(50) DEFAULT NULL,
            potential INT DEFAULT NULL,
            tel VARCHAR(20) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX IDX_sales_center_bid (bid_id),
            INDEX IDX_sales_center_owner (owner_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE sales_center
            ADD CONSTRAINT FK_sales_center_bid FOREIGN KEY (bid_id) REFERENCES business (id),
            ADD CONSTRAINT FK_sales_center_owner FOREIGN KEY (owner_id) REFERENCES user (id),
            ADD CONSTRAINT FK_sales_center_person FOREIGN KEY (person_id) REFERENCES person (id)
        ');

        $this->addSql('CREATE TABLE week_plan (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            center_id INT NOT NULL,
            submitter_id INT NOT NULL,
            owner_id INT DEFAULT NULL,
            week_str VARCHAR(20) NOT NULL,
            scheduled_date VARCHAR(20) NOT NULL,
            action_type VARCHAR(20) NOT NULL DEFAULT \'visit\',
            done TINYINT(1) NOT NULL DEFAULT 0,
            done_date VARCHAR(20) DEFAULT NULL,
            des LONGTEXT DEFAULT NULL,
            INDEX IDX_week_plan_bid (bid_id),
            INDEX IDX_week_plan_year (year_id),
            INDEX IDX_week_plan_center (center_id),
            INDEX IDX_week_plan_week (bid_id, year_id, week_str),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE week_plan
            ADD CONSTRAINT FK_week_plan_bid FOREIGN KEY (bid_id) REFERENCES business (id),
            ADD CONSTRAINT FK_week_plan_year FOREIGN KEY (year_id) REFERENCES year (id),
            ADD CONSTRAINT FK_week_plan_center FOREIGN KEY (center_id) REFERENCES sales_center (id),
            ADD CONSTRAINT FK_week_plan_submitter FOREIGN KEY (submitter_id) REFERENCES user (id),
            ADD CONSTRAINT FK_week_plan_owner FOREIGN KEY (owner_id) REFERENCES user (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE week_plan DROP FOREIGN KEY FK_week_plan_bid');
        $this->addSql('ALTER TABLE week_plan DROP FOREIGN KEY FK_week_plan_year');
        $this->addSql('ALTER TABLE week_plan DROP FOREIGN KEY FK_week_plan_center');
        $this->addSql('ALTER TABLE week_plan DROP FOREIGN KEY FK_week_plan_submitter');
        $this->addSql('ALTER TABLE week_plan DROP FOREIGN KEY FK_week_plan_owner');
        $this->addSql('DROP TABLE week_plan');

        $this->addSql('ALTER TABLE sales_center DROP FOREIGN KEY FK_sales_center_bid');
        $this->addSql('ALTER TABLE sales_center DROP FOREIGN KEY FK_sales_center_owner');
        $this->addSql('ALTER TABLE sales_center DROP FOREIGN KEY FK_sales_center_person');
        $this->addSql('DROP TABLE sales_center');
    }
}
