<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 20 columns missing from 5 tables (hesabdari_doc, hesabdari_row, pre_invoice_doc, print_options, storeroom_item)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $db = $this->connection->getDatabase();

        $cols = $this->connection->executeQuery(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS tbl_col
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db",
            ['db' => $db]
        )->fetchFirstColumn();
        $existing = array_flip($cols);

        $add = static function (string $key) use (&$existing): bool {
            return !isset($existing[$key]);
        };

        // ── hesabdari_doc ──────────────────────────────────────────────────
        if ($add('hesabdari_doc.tax_percent')) {
            $this->addSql('ALTER TABLE hesabdari_doc ADD tax_percent DOUBLE PRECISION DEFAULT NULL');
        }
        if ($add('hesabdari_doc.discount_type')) {
            $this->addSql('ALTER TABLE hesabdari_doc ADD discount_type VARCHAR(255) DEFAULT NULL');
        }
        if ($add('hesabdari_doc.discount_percent')) {
            $this->addSql('ALTER TABLE hesabdari_doc ADD discount_percent DECIMAL(10,2) DEFAULT NULL');
        }

        // ── hesabdari_row ──────────────────────────────────────────────────
        if ($add('hesabdari_row.discount_type')) {
            $this->addSql('ALTER TABLE hesabdari_row ADD discount_type VARCHAR(20) DEFAULT NULL');
        }
        if ($add('hesabdari_row.discount_percent')) {
            $this->addSql('ALTER TABLE hesabdari_row ADD discount_percent DECIMAL(10,2) DEFAULT NULL');
        }

        // ── pre_invoice_doc ────────────────────────────────────────────────
        if ($add('pre_invoice_doc.tax_percent')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD tax_percent VARCHAR(255) DEFAULT NULL');
        }
        if ($add('pre_invoice_doc.total_discount')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD total_discount VARCHAR(255) DEFAULT NULL');
        }
        if ($add('pre_invoice_doc.total_discount_percent')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD total_discount_percent VARCHAR(255) DEFAULT NULL');
        }
        if ($add('pre_invoice_doc.shipping_cost')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD shipping_cost VARCHAR(255) DEFAULT NULL');
        }
        if ($add('pre_invoice_doc.show_percent_discount')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD show_percent_discount TINYINT(1) DEFAULT NULL');
        }
        if ($add('pre_invoice_doc.show_total_percent_discount')) {
            $this->addSql('ALTER TABLE pre_invoice_doc ADD show_total_percent_discount TINYINT(1) DEFAULT NULL');
        }

        // ── print_options ──────────────────────────────────────────────────
        if ($add('print_options.left_footer')) {
            $this->addSql('ALTER TABLE print_options ADD left_footer LONGTEXT DEFAULT NULL');
        }
        if ($add('print_options.right_footer')) {
            $this->addSql('ALTER TABLE print_options ADD right_footer LONGTEXT DEFAULT NULL');
        }
        if ($add('print_options.sell_invoice_index')) {
            $this->addSql('ALTER TABLE print_options ADD sell_invoice_index TINYINT(1) DEFAULT NULL');
        }
        if ($add('print_options.sell_business_stamp')) {
            $this->addSql('ALTER TABLE print_options ADD sell_business_stamp TINYINT(1) DEFAULT NULL');
        }

        // ── storeroom_item ─────────────────────────────────────────────────
        if ($add('storeroom_item.lot_no')) {
            $this->addSql('ALTER TABLE storeroom_item ADD lot_no VARCHAR(255) DEFAULT NULL');
        }
        if ($add('storeroom_item.expiry_date')) {
            $this->addSql('ALTER TABLE storeroom_item ADD expiry_date VARCHAR(50) DEFAULT NULL');
        }
        if ($add('storeroom_item.imed_status')) {
            $this->addSql('ALTER TABLE storeroom_item ADD imed_status VARCHAR(20) DEFAULT NULL');
        }
        if ($add('storeroom_item.imed_ref')) {
            $this->addSql('ALTER TABLE storeroom_item ADD imed_ref VARCHAR(100) DEFAULT NULL');
        }
        if ($add('storeroom_item.sale_price')) {
            $this->addSql('ALTER TABLE storeroom_item ADD sale_price VARCHAR(50) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hesabdari_doc DROP COLUMN tax_percent, DROP COLUMN discount_type, DROP COLUMN discount_percent');
        $this->addSql('ALTER TABLE hesabdari_row DROP COLUMN discount_type, DROP COLUMN discount_percent');
        $this->addSql('ALTER TABLE pre_invoice_doc DROP COLUMN tax_percent, DROP COLUMN total_discount, DROP COLUMN total_discount_percent, DROP COLUMN shipping_cost, DROP COLUMN show_percent_discount, DROP COLUMN show_total_percent_discount');
        $this->addSql('ALTER TABLE print_options DROP COLUMN left_footer, DROP COLUMN right_footer, DROP COLUMN sell_invoice_index, DROP COLUMN sell_business_stamp');
        $this->addSql('ALTER TABLE storeroom_item DROP COLUMN lot_no, DROP COLUMN expiry_date, DROP COLUMN imed_status, DROP COLUMN imed_ref, DROP COLUMN sale_price');
    }
}
