<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sales_deal and visit_report tables for CRM pipeline';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sales_deal (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            center_id INT NOT NULL,
            person_id INT DEFAULT NULL,
            submitter_id INT NOT NULL,
            owner_id INT DEFAULT NULL,
            approved_by_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            stage VARCHAR(50) NOT NULL DEFAULT \'planning\',
            amount NUMERIC(30, 0) DEFAULT NULL,
            due_date VARCHAR(50) DEFAULT NULL,
            pre_invoice_id INT DEFAULT NULL,
            storeroom_ticket_id INT DEFAULT NULL,
            sell_doc_id INT DEFAULT NULL,
            approved_at VARCHAR(50) DEFAULT NULL,
            des LONGTEXT DEFAULT NULL,
            created_at VARCHAR(50) NOT NULL,
            closed_at VARCHAR(50) DEFAULT NULL,
            INDEX IDX_sales_deal_bid (bid_id),
            INDEX IDX_sales_deal_year (year_id),
            INDEX IDX_sales_deal_center (center_id),
            INDEX IDX_sales_deal_stage (bid_id, year_id, stage),
            INDEX IDX_sales_deal_due (due_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE sales_deal
            ADD CONSTRAINT FK_sales_deal_bid       FOREIGN KEY (bid_id)         REFERENCES business (id),
            ADD CONSTRAINT FK_sales_deal_year      FOREIGN KEY (year_id)        REFERENCES year (id),
            ADD CONSTRAINT FK_sales_deal_center    FOREIGN KEY (center_id)      REFERENCES sales_center (id),
            ADD CONSTRAINT FK_sales_deal_person    FOREIGN KEY (person_id)      REFERENCES person (id),
            ADD CONSTRAINT FK_sales_deal_submitter FOREIGN KEY (submitter_id)   REFERENCES user (id),
            ADD CONSTRAINT FK_sales_deal_owner     FOREIGN KEY (owner_id)       REFERENCES user (id),
            ADD CONSTRAINT FK_sales_deal_approved  FOREIGN KEY (approved_by_id) REFERENCES user (id)
        ');

        $this->addSql('CREATE TABLE visit_report (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            center_id INT NOT NULL,
            deal_id INT DEFAULT NULL,
            week_plan_id INT DEFAULT NULL,
            submitter_id INT NOT NULL,
            date VARCHAR(50) NOT NULL,
            result VARCHAR(50) NOT NULL DEFAULT \'follow_up\',
            des LONGTEXT DEFAULT NULL,
            next_action VARCHAR(255) DEFAULT NULL,
            next_date VARCHAR(50) DEFAULT NULL,
            INDEX IDX_visit_report_bid (bid_id),
            INDEX IDX_visit_report_center (center_id),
            INDEX IDX_visit_report_deal (deal_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE visit_report
            ADD CONSTRAINT FK_visit_report_bid       FOREIGN KEY (bid_id)       REFERENCES business (id),
            ADD CONSTRAINT FK_visit_report_center    FOREIGN KEY (center_id)    REFERENCES sales_center (id),
            ADD CONSTRAINT FK_visit_report_deal      FOREIGN KEY (deal_id)      REFERENCES sales_deal (id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_visit_report_weekplan  FOREIGN KEY (week_plan_id) REFERENCES week_plan (id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_visit_report_submitter FOREIGN KEY (submitter_id) REFERENCES user (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visit_report DROP FOREIGN KEY FK_visit_report_bid');
        $this->addSql('ALTER TABLE visit_report DROP FOREIGN KEY FK_visit_report_center');
        $this->addSql('ALTER TABLE visit_report DROP FOREIGN KEY FK_visit_report_deal');
        $this->addSql('ALTER TABLE visit_report DROP FOREIGN KEY FK_visit_report_weekplan');
        $this->addSql('ALTER TABLE visit_report DROP FOREIGN KEY FK_visit_report_submitter');
        $this->addSql('DROP TABLE visit_report');

        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_bid');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_year');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_center');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_person');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_submitter');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_owner');
        $this->addSql('ALTER TABLE sales_deal DROP FOREIGN KEY FK_sales_deal_approved');
        $this->addSql('DROP TABLE sales_deal');
    }
}
