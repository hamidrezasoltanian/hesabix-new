<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000007 extends AbstractMigration
{
    public function getDescription(): string { return 'WMS: storeroom count, purchase order, delivery, recall, IMED fields'; }

    public function up(Schema $schema): void
    {
        // ALTER storeroom_item: add IMED and sale price fields
        $this->addSql("ALTER TABLE storeroom_item
            ADD COLUMN imed_status VARCHAR(20) DEFAULT NULL,
            ADD COLUMN imed_ref VARCHAR(100) DEFAULT NULL,
            ADD COLUMN sale_price VARCHAR(50) DEFAULT NULL");

        // ALTER commodity: add extended fields
        $this->addSql("ALTER TABLE commodity
            ADD COLUMN irc_code VARCHAR(50) DEFAULT NULL,
            ADD COLUMN catalog_code VARCHAR(100) DEFAULT NULL,
            ADD COLUMN brand VARCHAR(100) DEFAULT NULL,
            ADD COLUMN full_name VARCHAR(500) DEFAULT NULL");

        // CREATE storeroom_count
        $this->addSql("CREATE TABLE storeroom_count (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            storeroom_id INT NOT NULL,
            date VARCHAR(20) NOT NULL,
            closed_date VARCHAR(20) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            note LONGTEXT DEFAULT NULL,
            created_by_id INT NOT NULL,
            INDEX IDX_SC_BID (bid_id),
            INDEX IDX_SC_STOREROOM (storeroom_id),
            INDEX IDX_SC_CREATED_BY (created_by_id),
            CONSTRAINT FK_SC_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_SC_STOREROOM FOREIGN KEY (storeroom_id) REFERENCES storeroom(id),
            CONSTRAINT FK_SC_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // CREATE storeroom_count_item
        $this->addSql("CREATE TABLE storeroom_count_item (
            id INT AUTO_INCREMENT NOT NULL,
            count_session_id INT NOT NULL,
            commodity_id INT NOT NULL,
            lot_no VARCHAR(100) DEFAULT NULL,
            expiry_date VARCHAR(20) DEFAULT NULL,
            system_qty VARCHAR(50) NOT NULL DEFAULT '0',
            physical_qty VARCHAR(50) NOT NULL DEFAULT '0',
            note VARCHAR(255) DEFAULT NULL,
            INDEX IDX_SCI_SESSION (count_session_id),
            INDEX IDX_SCI_COMMODITY (commodity_id),
            CONSTRAINT FK_SCI_SESSION FOREIGN KEY (count_session_id) REFERENCES storeroom_count(id) ON DELETE CASCADE,
            CONSTRAINT FK_SCI_COMMODITY FOREIGN KEY (commodity_id) REFERENCES commodity(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // CREATE purchase_order
        $this->addSql("CREATE TABLE purchase_order (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            code VARCHAR(50) NOT NULL,
            date VARCHAR(20) NOT NULL,
            person_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            notes LONGTEXT DEFAULT NULL,
            created_by_id INT NOT NULL,
            approved_by_id INT DEFAULT NULL,
            total_amount VARCHAR(50) DEFAULT NULL,
            INDEX IDX_PO_BID (bid_id),
            INDEX IDX_PO_YEAR (year_id),
            INDEX IDX_PO_PERSON (person_id),
            INDEX IDX_PO_CREATED_BY (created_by_id),
            INDEX IDX_PO_APPROVED_BY (approved_by_id),
            CONSTRAINT FK_PO_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_PO_YEAR FOREIGN KEY (year_id) REFERENCES year(id),
            CONSTRAINT FK_PO_PERSON FOREIGN KEY (person_id) REFERENCES person(id),
            CONSTRAINT FK_PO_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user(id),
            CONSTRAINT FK_PO_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES user(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // CREATE purchase_order_item
        $this->addSql("CREATE TABLE purchase_order_item (
            id INT AUTO_INCREMENT NOT NULL,
            po_id INT NOT NULL,
            commodity_id INT NOT NULL,
            qty VARCHAR(50) NOT NULL,
            unit_price VARCHAR(50) DEFAULT NULL,
            received_qty VARCHAR(50) NOT NULL DEFAULT '0',
            notes VARCHAR(255) DEFAULT NULL,
            INDEX IDX_POI_PO (po_id),
            INDEX IDX_POI_COMMODITY (commodity_id),
            CONSTRAINT FK_POI_PO FOREIGN KEY (po_id) REFERENCES purchase_order(id) ON DELETE CASCADE,
            CONSTRAINT FK_POI_COMMODITY FOREIGN KEY (commodity_id) REFERENCES commodity(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // CREATE delivery_record
        $this->addSql("CREATE TABLE delivery_record (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            ticket_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            courier VARCHAR(50) DEFAULT NULL,
            tracking_code VARCHAR(100) DEFAULT NULL,
            recipient_name VARCHAR(255) DEFAULT NULL,
            recipient_tel VARCHAR(50) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            sent_at VARCHAR(20) DEFAULT NULL,
            delivered_at VARCHAR(20) DEFAULT NULL,
            INDEX IDX_DR_BID (bid_id),
            INDEX IDX_DR_YEAR (year_id),
            INDEX IDX_DR_TICKET (ticket_id),
            CONSTRAINT FK_DR_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_DR_YEAR FOREIGN KEY (year_id) REFERENCES year(id),
            CONSTRAINT FK_DR_TICKET FOREIGN KEY (ticket_id) REFERENCES storeroom_ticket(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");

        // CREATE storeroom_recall
        $this->addSql("CREATE TABLE storeroom_recall (
            id INT AUTO_INCREMENT NOT NULL,
            bid_id INT NOT NULL,
            year_id INT NOT NULL,
            commodity_id INT NOT NULL,
            lot_no VARCHAR(100) DEFAULT NULL,
            reason LONGTEXT DEFAULT NULL,
            priority VARCHAR(10) NOT NULL DEFAULT 'medium',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            start_date VARCHAR(20) NOT NULL,
            end_date VARCHAR(20) DEFAULT NULL,
            affected_count VARCHAR(50) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            INDEX IDX_SR_BID (bid_id),
            INDEX IDX_SR_YEAR (year_id),
            INDEX IDX_SR_COMMODITY (commodity_id),
            CONSTRAINT FK_SR_BID FOREIGN KEY (bid_id) REFERENCES business(id),
            CONSTRAINT FK_SR_YEAR FOREIGN KEY (year_id) REFERENCES year(id),
            CONSTRAINT FK_SR_COMMODITY FOREIGN KEY (commodity_id) REFERENCES commodity(id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE storeroom_recall');
        $this->addSql('DROP TABLE delivery_record');
        $this->addSql('DROP TABLE purchase_order_item');
        $this->addSql('DROP TABLE purchase_order');
        $this->addSql('DROP TABLE storeroom_count_item');
        $this->addSql('DROP TABLE storeroom_count');
        $this->addSql('ALTER TABLE commodity DROP COLUMN irc_code, DROP COLUMN catalog_code, DROP COLUMN brand, DROP COLUMN full_name');
        $this->addSql('ALTER TABLE storeroom_item DROP COLUMN imed_status, DROP COLUMN imed_ref, DROP COLUMN sale_price');
    }
}
