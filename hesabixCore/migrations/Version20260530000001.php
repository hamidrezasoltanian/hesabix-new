<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000001 extends AbstractMigration
{
    public function getDescription(): string { return 'Deal tasks, activity log, priority field'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales_deal ADD priority VARCHAR(10) DEFAULT NULL");

        $this->addSql("CREATE TABLE deal_task (
            id INT AUTO_INCREMENT NOT NULL,
            deal_id INT NOT NULL,
            bid_id INT NOT NULL,
            done_by_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            done_at VARCHAR(50) DEFAULT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_at VARCHAR(50) NOT NULL,
            INDEX IDX_DT_DEAL (deal_id),
            INDEX IDX_DT_BID (bid_id),
            CONSTRAINT FK_DT_DEAL FOREIGN KEY (deal_id) REFERENCES sales_deal(id) ON DELETE CASCADE,
            CONSTRAINT FK_DT_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_DT_DONE_BY FOREIGN KEY (done_by_id) REFERENCES user(id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        $this->addSql("CREATE TABLE deal_activity (
            id INT AUTO_INCREMENT NOT NULL,
            deal_id INT NOT NULL,
            bid_id INT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(30) NOT NULL,
            content LONGTEXT NOT NULL,
            date VARCHAR(50) NOT NULL,
            INDEX IDX_DA_DEAL (deal_id),
            INDEX IDX_DA_BID (bid_id),
            CONSTRAINT FK_DA_DEAL FOREIGN KEY (deal_id) REFERENCES sales_deal(id) ON DELETE CASCADE,
            CONSTRAINT FK_DA_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_DA_USER FOREIGN KEY (user_id) REFERENCES user(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE deal_activity');
        $this->addSql('DROP TABLE deal_task');
        $this->addSql('ALTER TABLE sales_deal DROP COLUMN priority');
    }
}
