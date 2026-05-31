<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable all plugins by default and seed missing plugin products for self-hosted deployment';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Enable all existing plugin products by default (no payment required)
        $this->addSql("UPDATE plugin_prodect SET default_on = 1");

        // Seed any missing plugin products and enable them
        $db = $this->connection->getDatabase();

        $existing = $this->connection->executeQuery(
            "SELECT code FROM plugin_prodect",
        )->fetchFirstColumn();

        $products = [
            ['accpro',      'بسته حسابداری پیشرفته',            '99999999', 'نامحدود', '0', 'accpro.png'],
            ['repservice',  'مدیریت تعمیرگاه',                   '99999999', 'نامحدود', '0', 'repservice.jpg'],
            ['hrm',         'مدیریت منابع انسانی',               '99999999', 'نامحدود', '0', 'hrm.png'],
            ['ghesta',      'مدیریت اقساط',                      '99999999', 'نامحدود', '0', 'ghesta.png'],
            ['noghre',      'افزونه نقره',                       '99999999', 'نامحدود', '0', 'noghre.png'],
            ['ccadmin',     'مدیریت کارت اعتباری',               '99999999', 'نامحدود', '0', 'ccadmin.png'],
        ];

        foreach ($products as [$code, $name, $timestamp, $timelabel, $price, $icon]) {
            if (!in_array($code, $existing, true)) {
                $this->addSql(
                    "INSERT INTO plugin_prodect (name, code, timestamp, timelabel, price, icon, default_on) VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$name, $code, $timestamp, $timelabel, $price, $icon]
                );
            }
        }

        // Set unlimited duration for the accounting package
        $this->addSql("UPDATE registry SET value_of_key = '1' WHERE root = 'system_settings' AND name = 'unlimited_duration'");
        $this->addSql("UPDATE registry SET value_of_key = '0' WHERE root = 'system_settings' AND name = 'unlimited_price'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE plugin_prodect SET default_on = NULL");
    }
}
