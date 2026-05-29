<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000006 extends AbstractMigration
{
    public function getDescription(): string { return 'CRM Calendar, Checklist, KPI tables'; }

    public function up(Schema $schema): void
    {
        // crm_calendar_event
        $this->addSql("CREATE TABLE crm_calendar_event (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL, user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            date VARCHAR(20) NOT NULL,
            end_date VARCHAR(20) DEFAULT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#1976D2',
            all_day TINYINT(1) NOT NULL DEFAULT 0,
            des LONGTEXT DEFAULT NULL,
            INDEX IDX_CAL_BID (bid_id), INDEX IDX_CAL_USER (user_id), INDEX IDX_CAL_DATE (date),
            CONSTRAINT FK_CAL_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_CAL_USER FOREIGN KEY (user_id) REFERENCES user(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // crm_checklist_item
        $this->addSql("CREATE TABLE crm_checklist_item (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            manager_only TINYINT(1) NOT NULL DEFAULT 0,
            display_order INT NOT NULL DEFAULT 0,
            score INT NOT NULL DEFAULT 1,
            INDEX IDX_CLI_BID (bid_id),
            CONSTRAINT FK_CLI_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // crm_checklist_log
        $this->addSql("CREATE TABLE crm_checklist_log (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL, user_id INT NOT NULL, item_id INT NOT NULL,
            date VARCHAR(20) NOT NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            note VARCHAR(500) DEFAULT NULL,
            UNIQUE KEY uniq_checklist_log (bid_id, user_id, date, item_id),
            INDEX IDX_CLL_BID (bid_id), INDEX IDX_CLL_USER (user_id), INDEX IDX_CLL_DATE (date),
            CONSTRAINT FK_CLL_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_CLL_USER FOREIGN KEY (user_id) REFERENCES user(id),
            CONSTRAINT FK_CLL_ITEM FOREIGN KEY (item_id) REFERENCES crm_checklist_item(id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // kpi_target
        $this->addSql("CREATE TABLE kpi_target (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL, user_id INT NOT NULL,
            month VARCHAR(10) NOT NULL,
            call_daily_target INT NOT NULL DEFAULT 10,
            visit_weekly_target INT NOT NULL DEFAULT 5,
            sale_monthly_target INT NOT NULL DEFAULT 2,
            sale_amount_target VARCHAR(30) NOT NULL DEFAULT '0',
            retention_pct_target INT NOT NULL DEFAULT 80,
            mission_monthly_target INT NOT NULL DEFAULT 2,
            cash_pct_target INT NOT NULL DEFAULT 30,
            UNIQUE KEY uniq_kpi_target (bid_id, user_id, month),
            INDEX IDX_KPI_BID (bid_id), INDEX IDX_KPI_USER (user_id),
            CONSTRAINT FK_KPI_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_KPI_USER FOREIGN KEY (user_id) REFERENCES user(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kpi_target');
        $this->addSql('DROP TABLE crm_checklist_log');
        $this->addSql('DROP TABLE crm_checklist_item');
        $this->addSql('DROP TABLE crm_calendar_event');
    }
}
