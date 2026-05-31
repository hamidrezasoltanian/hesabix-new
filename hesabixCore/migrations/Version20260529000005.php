<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CRM fields on SalesCenter + SalesCenterTag + ActivityLog tables';
    }

    public function up(Schema $schema): void
    {
        // ── 1. New CRM columns on sales_center ────────────────────────────────
        $this->addSql("ALTER TABLE sales_center
            ADD `lead` VARCHAR(30) DEFAULT NULL,
            ADD crm_status VARCHAR(30) DEFAULT 'no_contact',
            ADD followup_date VARCHAR(20) DEFAULT NULL");

        // ── 2. sales_center_tag ───────────────────────────────────────────────
        $this->addSql("CREATE TABLE sales_center_tag (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#1976D2',
            INDEX IDX_TAG_BID (bid_id),
            CONSTRAINT FK_TAG_BID FOREIGN KEY (bid_id) REFERENCES business (id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        // ── 3. Junction table: sales_center ↔ sales_center_tag ────────────────
        $this->addSql("CREATE TABLE sales_center_tag_assignment (
            sales_center_id INT NOT NULL,
            sales_center_tag_id INT NOT NULL,
            INDEX IDX_ASSIGN_CENTER (sales_center_id),
            INDEX IDX_ASSIGN_TAG (sales_center_tag_id),
            CONSTRAINT FK_ASSIGN_CENTER FOREIGN KEY (sales_center_id) REFERENCES sales_center (id) ON DELETE CASCADE,
            CONSTRAINT FK_ASSIGN_TAG FOREIGN KEY (sales_center_tag_id) REFERENCES sales_center_tag (id) ON DELETE CASCADE,
            PRIMARY KEY (sales_center_id, sales_center_tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        // ── 4. activity_log ───────────────────────────────────────────────────
        $this->addSql("CREATE TABLE activity_log (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            center_id INT DEFAULT NULL,
            user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            date VARCHAR(20) NOT NULL,
            amount VARCHAR(30) DEFAULT NULL,
            cash_sale TINYINT(1) NOT NULL DEFAULT 0,
            done TINYINT(1) NOT NULL DEFAULT 0,
            note LONGTEXT DEFAULT NULL,
            created_at VARCHAR(20) DEFAULT NULL,
            INDEX IDX_AL_BID (bid_id),
            INDEX IDX_AL_YEAR (year_id),
            INDEX IDX_AL_CENTER (center_id),
            INDEX IDX_AL_USER (user_id),
            INDEX IDX_AL_DATE (date),
            CONSTRAINT FK_AL_BID FOREIGN KEY (bid_id) REFERENCES business (id),
            CONSTRAINT FK_AL_YEAR FOREIGN KEY (year_id) REFERENCES year (id),
            CONSTRAINT FK_AL_CENTER FOREIGN KEY (center_id) REFERENCES sales_center (id) ON DELETE SET NULL,
            CONSTRAINT FK_AL_USER FOREIGN KEY (user_id) REFERENCES user (id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        // ── 5. Seed 8 default tags (shared / bid_id = NULL not possible due to FK)
        //       Tags are per-business so seeding happens at business creation time.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE sales_center_tag_assignment');
        $this->addSql('DROP TABLE sales_center_tag');
        $this->addSql('ALTER TABLE sales_center DROP COLUMN `lead`, DROP COLUMN crm_status, DROP COLUMN followup_date');
    }
}
