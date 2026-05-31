<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed all remaining registry keys accessed by registryMGR::get()';
    }

    public function up(Schema $schema): void
    {
        // INSERT IGNORE — idempotent, safe to re-run.
        // registryMGR::get() INSERTs a blank row whenever a key is missing.
        // On a fresh install that INSERT was intermittently failing (500).
        // Pre-seeding every key the app reads prevents any runtime INSERT.

        // system keys used in registration, UI, and settings
        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('system', 'footerLeft',         ''),
            ('system', 'footerRight',        ''),
            ('system', 'sponsers',           ''),
            ('system', 'verifyMobileViaSms', '0')");

        // system_settings keys
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

        // sms keys
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

        // ticket keys
        $this->addSql("INSERT IGNORE INTO registry (root, name, value_of_key) VALUES
            ('ticket', 'managerMobile', '')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM registry WHERE root IN ('ticket') AND name IN ('managerMobile')");
        $this->addSql("DELETE FROM registry WHERE root = 'system' AND name IN ('footerLeft','footerRight','verifyMobileViaSms')");
        $this->addSql("DELETE FROM registry WHERE root = 'system_settings' AND name IN ('unlimited_duration','unlimited_price','ftp_enabled','ftp_host','ftp_port','ftp_username','ftp_password','ftp_path')");
    }
}
