<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix chart of accounts: typo, trim names, add missing accounts, soft delete, per-business code uniqueness';
    }

    public function up(Schema $schema): void
    {
        // ── 0. Ensure id has AUTO_INCREMENT (dump sets it separately; may be missing) ──
        $this->addSql('ALTER TABLE hesabdari_table MODIFY id INT NOT NULL AUTO_INCREMENT');

        // ── 1. Soft delete column (idempotent: skip if already added by a partial run) ──
        // NOTE: $schema is NOT the current DB state in Doctrine Migrations 3 — use connection directly
        $activeExists = (bool) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'hesabdari_table' AND column_name = 'active'"
        )->fetchOne();
        if (!$activeExists) {
            $this->addSql('ALTER TABLE hesabdari_table ADD active TINYINT(1) NOT NULL DEFAULT 1');
        }

        // ── 2. Fix root typo: "جدول جساب" → "جدول حساب" ──────────────────
        $this->addSql("UPDATE hesabdari_table SET name = 'جدول حساب' WHERE id = 1");

        // ── 3. Trim leading/trailing whitespace from all account names ─────
        $this->addSql('UPDATE hesabdari_table SET name = TRIM(name)');

        // ── 4. Add missing standard accounts (INSERT IGNORE handles reruns) ─

        // 4a. پیش‌پرداخت‌ها — under دارایی‌های جاری (code='2', id=2)
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            VALUES (2, 'پیش‌پرداخت‌ها', 'calc', '126', NULL, NULL, 1)");

        // 4b. پیش‌پرداخت خرید — under پیش‌پرداخت‌ها (code='126')
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            SELECT id, 'پیش‌پرداخت خرید', 'calc', '127', NULL, NULL, 1
            FROM hesabdari_table WHERE code = '126' AND bid_id IS NULL LIMIT 1");

        // 4c. سایر پیش‌پرداخت‌ها — under پیش‌پرداخت‌ها (code='126')
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            SELECT id, 'سایر پیش‌پرداخت‌ها', 'calc', '128', NULL, NULL, 1
            FROM hesabdari_table WHERE code = '126' AND bid_id IS NULL LIMIT 1");

        // 4d. اسناد دریافتنی — under دارایی‌های جاری (code='2', id=2)
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            VALUES (2, 'اسناد دریافتنی', 'person', '129', NULL, NULL, 1)");

        // 4e. مالیات بر ارزش افزوده خرید — under دارایی‌های جاری (code='2', id=2)
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            VALUES (2, 'مالیات بر ارزش افزوده خرید', 'calc', '130', NULL, NULL, 1)");

        // 4f. چک‌های پرداختنی — under بدهی‌های جاری (code='6', id=6)
        $this->addSql("INSERT IGNORE INTO hesabdari_table (upper_id, name, type, code, entity, bid_id, active)
            VALUES (6, 'چک‌های پرداختنی', 'cheque', '131', 'Cheque', NULL, 1)");

        // ── 5. Replace global UNIQUE on code with per-business functional unique ──

        // Drop old global unique only if it still exists
        $oldIndexExists = (bool) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'hesabdari_table'
             AND index_name = 'UNIQ_40F7185C77153098'"
        )->fetchOne();
        if ($oldIndexExists) {
            $this->addSql('ALTER TABLE hesabdari_table DROP INDEX UNIQ_40F7185C77153098');
        }

        // Add functional unique only if it doesn't exist yet
        $newIndexExists = (bool) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'hesabdari_table'
             AND index_name = 'uniq_code_bid'"
        )->fetchOne();
        if (!$newIndexExists) {
            $this->addSql('ALTER TABLE hesabdari_table ADD UNIQUE KEY uniq_code_bid ((COALESCE(`bid_id`, 0)), `code`)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hesabdari_table DROP INDEX uniq_code_bid');
        $this->addSql('ALTER TABLE hesabdari_table ADD UNIQUE KEY UNIQ_40F7185C77153098 (code)');
        $this->addSql('DELETE FROM hesabdari_table WHERE code IN (\'126\',\'127\',\'128\',\'129\',\'130\',\'131\') AND bid_id IS NULL');
        $this->addSql("UPDATE hesabdari_table SET name = 'جدول جساب' WHERE id = 1");
        $this->addSql('ALTER TABLE hesabdari_table DROP COLUMN active');
        $this->addSql('ALTER TABLE hesabdari_table MODIFY id INT NOT NULL');
    }
}
