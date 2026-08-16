<?php

namespace App\Integration;

use PDO;
use RuntimeException;

/**
 * Account-only journal insert on the same PDO transaction as ClickCrmHesabdariDoc.
 * Never writes commodity / storeroom / sell / buy rows. No unique index on ref_data.
 */
class ClickCrmJournalPersister
{
    private PDO $pdo;
    private int $businessId;
    private int $yearId;
    private int $moneyId;
    private int $submitterId;

    public function __construct(PDO $pdo, int $businessId, int $yearId, int $moneyId, int $submitterId)
    {
        $this->pdo = $pdo;
        $this->businessId = $businessId;
        $this->yearId = $yearId;
        $this->moneyId = $moneyId;
        $this->submitterId = $submitterId;
    }

    public function persist(array $body): array
    {
        $journal = isset($body['journal']) && is_array($body['journal']) ? $body['journal'] : array();
        $lines = isset($journal['lines']) && is_array($journal['lines']) ? $journal['lines'] : array();
        if (count($lines) < 2) {
            throw new RuntimeException('JOURNAL_LINES_REQUIRED');
        }
        if (ClickCrmHesabdariDoc::linesHaveCommodity($lines)) {
            throw new RuntimeException('COMMODITY_FORBIDDEN');
        }

        $eventId = isset($body['event_id']) ? (string)$body['event_id'] : '';
        $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : array();
        $date = $this->resolveDate($journal, $payload);
        $des = isset($journal['description']) ? (string)$journal['description'] : ('click-crm ' . $eventId);
        $code = $this->nextDocCode();
        $amount = ClickCrmHesabdariDoc::balance($lines)['debit'];

        $st = $this->pdo->prepare(
            'INSERT INTO hesabdari_doc
               (bid_id, submitter_id, date_submit, date, type, code, year_id, des, amount, money_id, ref_data, plugin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute(array(
            $this->businessId,
            $this->submitterId,
            (string) time(),
            $date,
            'doc',
            $code,
            $this->yearId,
            $des,
            (string) $amount,
            $this->moneyId,
            $eventId !== '' ? $eventId : null,
            'click-crm',
        ));
        $docId = (int) $this->pdo->lastInsertId();
        if ($docId <= 0) {
            throw new RuntimeException('DOC_ID_REQUIRED');
        }

        $rowSql = $this->pdo->prepare(
            'INSERT INTO hesabdari_row
               (doc_id, bs, bd, ref_id, person_id, bank_id, des, bid_id, year_id, cashdesk_id, referral, ref_data, plugin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($lines as $line) {
            if (!empty($line['commodity']) || !empty($line['product']) || !empty($line['storeroom'])) {
                throw new RuntimeException('COMMODITY_FORBIDDEN');
            }
            $refId = $this->resolveAccountId(isset($line['account']) ? $line['account'] : null);
            $debit = isset($line['debit']) ? (float) $line['debit'] : 0;
            $credit = isset($line['credit']) ? (float) $line['credit'] : 0;
            $personId = $this->optionalEntityId('person', isset($line['person']) ? $line['person'] : null);
            $bankId = $this->optionalEntityId('bank_account', isset($line['bank']) ? $line['bank'] : null);
            $cashdeskId = $this->optionalEntityId('cashdesk', isset($line['cashdesk']) ? $line['cashdesk'] : null);
            $rowDes = isset($line['description']) ? (string) $line['description'] : null;
            $rowSql->execute(array(
                $docId,
                $this->amountString($credit),
                $this->amountString($debit),
                $refId,
                $personId,
                $bankId,
                $rowDes,
                $this->businessId,
                $this->yearId,
                $cashdeskId,
                $eventId !== '' ? $eventId : null,
                $eventId !== '' ? $eventId : null,
                'click-crm',
            ));
        }

        return array('id' => (string) $docId, 'code' => $code);
    }

    private function nextDocCode(): string
    {
        $st = $this->pdo->prepare(
            'SELECT code FROM hesabdari_doc WHERE bid_id = ? ORDER BY CAST(code AS UNSIGNED) DESC LIMIT 1 FOR UPDATE'
        );
        $st->execute(array($this->businessId));
        $last = $st->fetchColumn();
        $n = $last ? ((int) $last + 1) : 1;
        return (string) $n;
    }

    private function resolveAccountId($account): int
    {
        $raw = trim((string) $account);
        if ($raw === '') {
            throw new RuntimeException('JOURNAL_ACCOUNT_NOT_FOUND');
        }
        $st = $this->pdo->prepare(
            'SELECT id FROM hesabdari_table
              WHERE code = ? AND (bid_id IS NULL OR bid_id = ?)
              LIMIT 1'
        );
        $st->execute(array($raw, $this->businessId));
        $id = $st->fetchColumn();
        if (!$id && ctype_digit($raw)) {
            $st = $this->pdo->prepare(
                'SELECT id FROM hesabdari_table
                  WHERE id = ? AND (bid_id IS NULL OR bid_id = ?)
                  LIMIT 1'
            );
            $st->execute(array((int) $raw, $this->businessId));
            $id = $st->fetchColumn();
        }
        if (!$id) {
            throw new RuntimeException('JOURNAL_ACCOUNT_NOT_FOUND');
        }
        return (int) $id;
    }

    private function optionalEntityId(string $kind, $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $id = (int) $value;
        if ($id <= 0) {
            throw new RuntimeException(strtoupper($kind) . '_UNMAPPED');
        }
        if ($kind === 'person') {
            $st = $this->pdo->prepare('SELECT id FROM person WHERE id = ? AND bid_id = ? LIMIT 1');
            $code = 'PERSON_UNMAPPED';
        } elseif ($kind === 'bank_account') {
            $st = $this->pdo->prepare('SELECT id FROM bank_account WHERE id = ? AND bid_id = ? LIMIT 1');
            $code = 'BANK_UNMAPPED';
        } else {
            $st = $this->pdo->prepare('SELECT id FROM cashdesk WHERE id = ? AND bid_id = ? LIMIT 1');
            $code = 'CASHDESK_UNMAPPED';
        }
        $st->execute(array($id, $this->businessId));
        if (!$st->fetchColumn()) {
            throw new RuntimeException($code);
        }
        return $id;
    }

    private function resolveDate(array $journal, array $payload): string
    {
        foreach (array('date', 'jalaliDate', 'jalali_date') as $key) {
            if (!empty($journal[$key])) {
                return (string) $journal[$key];
            }
            if (!empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }
        return date('Y/m/d');
    }

    private function amountString(float $n): string
    {
        $rounded = round($n, 2);
        if (abs($rounded - round($rounded)) < 0.00001) {
            return (string) (int) round($rounded);
        }
        return (string) $rounded;
    }
}
