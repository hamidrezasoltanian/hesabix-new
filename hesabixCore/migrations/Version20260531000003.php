<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix missing AUTO_INCREMENT on all id columns; add unique key to registry; re-seed registry';
    }

    // DDL causes implicit commits in MySQL — there is nothing to roll back.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // ── 0. Bypass FK checks so we can MODIFY columns that child tables reference ──
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        // ── 1. Find every int `id` column that lacks AUTO_INCREMENT ──
        // The SQL dump's AUTO_INCREMENT MODIFY statements run at the very end of the
        // file; if the import was interrupted or the volume was pre-existing those
        // statements never executed, leaving every table without AUTO_INCREMENT.
        // Doctrine's #[ORM\GeneratedValue] omits `id` from INSERTs expecting MySQL to
        // auto-generate it — without AUTO_INCREMENT every INSERT fails with
        // "Field id doesn't have a default value".
        $db = $this->connection->getDatabase();

        $rows = $this->connection->executeQuery(
            "SELECT TABLE_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND COLUMN_NAME  = 'id'
               AND DATA_TYPE    = 'int'
               AND EXTRA NOT LIKE '%auto_increment%'
             ORDER BY TABLE_NAME",
            ['db' => $db]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $table = $row['TABLE_NAME'];

            // When AUTO_INCREMENT is missing, MySQL stores 0 for int NOT NULL columns
            // that are omitted from an INSERT (or INSERT IGNORE silences the error and
            // uses 0).  Adding AUTO_INCREMENT later causes MySQL to renumber those
            // zero-id rows starting from 1, which collides with any real id=1 row
            // already in the table.  Delete them first — they are invalid placeholder
            // rows that will be re-inserted correctly once AUTO_INCREMENT is in place.
            $this->addSql("DELETE FROM `{$table}` WHERE id = 0");

            $this->addSql("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
        }

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');

        // ── 2. Add a unique key to registry so INSERT IGNORE is truly idempotent ──
        // Without this, repeated INSERT IGNORE calls create duplicate (root, name) rows
        // because the only unique constraint is on id.
        $hasUniqueKey = (int) $this->connection->executeQuery(
            "SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME   = 'registry'
               AND INDEX_NAME   = 'uq_registry_root_name'",
            ['db' => $db]
        )->fetchOne();

        if (!$hasUniqueKey) {
            $this->addSql('ALTER TABLE registry ADD UNIQUE KEY uq_registry_root_name (root, name)');
        }

        // ── 3. Re-seed all registry keys ──
        // INSERT IGNORE is now safe (unique key prevents duplicates).
        // This restores any rows deleted in step 1 and fills in keys that were
        // silently discarded by earlier INSERT IGNOREs that ran without AUTO_INCREMENT.
        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('system', 'appName',   'Hesabix'),
            ('system', 'appSlogan', ''),
            ('system', 'appUrl',    ''),
            ('system', 'footerLeft',         ''),
            ('system', 'footerRight',        ''),
            ('system', 'sponsers',           ''),
            ('system', 'verifyMobileViaSms', '0')");

        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('system_settings', 'can_register',         '1'),
            ('system_settings', 'can_free_accounting',  '1'),
            ('system_settings', 'canFreeAccounting',    '1'),
            ('system_settings', 'gift_credit',          '0'),
            ('system_settings', 'accounting_doc_price', '0'),
            ('system_settings', 'unlimited_duration',   '0'),
            ('system_settings', 'unlimited_price',      '0'),
            ('system_settings', 'ftp_enabled',          '0'),
            ('system_settings', 'ftp_host',             ''),
            ('system_settings', 'ftp_port',             '21'),
            ('system_settings', 'ftp_username',         ''),
            ('system_settings', 'ftp_password',         ''),
            ('system_settings', 'ftp_path',             '')");

        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('sms', 'token',                              ''),
            ('sms', 'username',                           ''),
            ('sms', 'password',                           ''),
            ('sms', 'fromNum',                            ''),
            ('sms', 'changePassword',                     ''),
            ('sms', 'chequeInput',                        ''),
            ('sms', 'chequeReject',                       ''),
            ('sms', 'chequeTransfer',                     ''),
            ('sms', 'passChequeInput',                    ''),
            ('sms', 'rejectChequeInput',                  ''),
            ('sms', 'recPassword',                        ''),
            ('sms', 'sharefaktor',                        ''),
            ('sms', 'ticketRec',                          ''),
            ('sms', 'ticketReplay',                       ''),
            ('sms', 'walletPay',                          ''),
            ('sms', 'walletpay',                          ''),
            ('sms', 'plugAccproChequeInput',              ''),
            ('sms', 'plugAccproChequeReject',             ''),
            ('sms', 'plugAccproChequeTransfer',           ''),
            ('sms', 'plugAccproPassChequeInput',          ''),
            ('sms', 'plugAccproRejectChequeInput',        ''),
            ('sms', 'plugAccproSharefaktor',              ''),
            ('sms', 'plugAccproStoreroomSmsBarbari',      ''),
            ('sms', 'plugAccproStoreroomSmsOther',        ''),
            ('sms', 'plugRepserviceStateCreated',         ''),
            ('sms', 'plugRepserviceStateCreating',        ''),
            ('sms', 'plugRepserviceStateGet',             ''),
            ('sms', 'plugRepserviceStateGetback',         ''),
            ('sms', 'plugRepserviceStateRepaired',        ''),
            ('sms', 'plugRepserviceStateUnrepired',       '')");

        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('ticket', 'managerMobile', '')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registry DROP INDEX uq_registry_root_name');
    }
}
