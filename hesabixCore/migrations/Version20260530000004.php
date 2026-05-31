<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_note table and permission_template table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_note (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            person_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            type VARCHAR(20) NOT NULL DEFAULT \'note\',
            content LONGTEXT NOT NULL,
            reminder_date VARCHAR(10) DEFAULT NULL,
            reminder_sent TINYINT(1) DEFAULT 0,
            date VARCHAR(10) NOT NULL,
            mentioned_mobile VARCHAR(255) DEFAULT NULL,
            INDEX IDX_CNOTE_BID (bid_id),
            INDEX IDX_CNOTE_PERSON (person_id),
            INDEX IDX_CNOTE_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE customer_note
            ADD CONSTRAINT FK_customer_note_bid FOREIGN KEY (bid_id) REFERENCES business (id),
            ADD CONSTRAINT FK_customer_note_person FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_customer_note_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE permission_template (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            permissions JSON NOT NULL,
            INDEX IDX_PERM_TPL_BID (bid_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE permission_template
            ADD CONSTRAINT FK_permission_template_bid FOREIGN KEY (bid_id) REFERENCES business (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_note DROP FOREIGN KEY FK_customer_note_bid');
        $this->addSql('ALTER TABLE customer_note DROP FOREIGN KEY FK_customer_note_person');
        $this->addSql('ALTER TABLE customer_note DROP FOREIGN KEY FK_customer_note_user');
        $this->addSql('DROP TABLE customer_note');
        $this->addSql('ALTER TABLE permission_template DROP FOREIGN KEY FK_permission_template_bid');
        $this->addSql('DROP TABLE permission_template');
    }
}
