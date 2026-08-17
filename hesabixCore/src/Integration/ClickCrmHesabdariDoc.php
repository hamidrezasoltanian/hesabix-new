<?php

namespace App\Integration;

use Exception;
use PDO;
use PDOException;

/**
 * Drop-in replay + journal helper for Hesabix POST /api/hesabdari/doc.
 * Apply on hamidrezasoltanian/hesabix-new Pilot only. Do not enable on Production
 * until Gate H. Do not add a unique index on refData. Do not call sell/buy/storeroom/insert.
 *
 * persistDoc MUST use this same $pdo (already in a transaction). Do not open a
 * second Doctrine/EntityManager connection or the receipt and journal split.
 */
class ClickCrmHesabdariDoc
{
    const SOURCE = 'click-crm';

    private $pdo;
    private $businessId;

    public function __construct(PDO $pdo, $businessId)
    {
        $this->pdo = $pdo;
        $this->businessId = (int)$businessId;
    }

    public static function linesHaveCommodity(array $lines)
    {
        foreach ($lines as $line) {
            if (!empty($line['commodity']) || !empty($line['product']) || !empty($line['storeroom'])) {
                return true;
            }
        }
        return false;
    }

    public static function balance(array $lines)
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += isset($line['debit']) ? (float)$line['debit'] : 0;
            $credit += isset($line['credit']) ? (float)$line['credit'] : 0;
        }
        return array(
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        );
    }

    public function handle(array $body)
    {
        $eventId = isset($body['event_id']) ? (string)$body['event_id'] : '';
        $hash = isset($body['payload_hash']) ? (string)$body['payload_hash'] : '';
        if ($eventId === '' || $hash === '') {
            return array('status' => 400, 'body' => array('code' => 'EVENT_ID_REQUIRED'));
        }
        $existing = $this->findReceipt($eventId);
        if ($existing) {
            if ($existing['payload_hash'] === $hash) {
                return array(
                    'status' => 200,
                    'replay' => true,
                    'body' => array('id' => $existing['doc_id'], 'code' => $existing['doc_code']),
                );
            }
            return array(
                'status' => 409,
                'replay' => false,
                'body' => array(
                    'code' => 'PAYLOAD_CONFLICT',
                    'event_id' => $eventId,
                    'existing_doc_id' => $existing['doc_id'],
                    'existing_payload_hash' => $existing['payload_hash'],
                ),
            );
        }
        return array('status' => 200, 'replay' => false, 'body' => array('insert' => true, 'event_id' => $eventId));
    }

    /**
     * Receipt + journal persist in one transaction. $persistDoc must return array(id, code)
     * and write person/bank/cashdesk on each journal line. No commodity/storeroom.
     */
    public function commitEvent(array $body, $persistDoc)
    {
        $journal = isset($body['journal']) && is_array($body['journal']) ? $body['journal'] : array();
        $lines = isset($journal['lines']) && is_array($journal['lines']) ? $journal['lines'] : array();
        if (self::linesHaveCommodity($lines)) {
            return array('status' => 400, 'body' => array('code' => 'COMMODITY_FORBIDDEN'));
        }
        $totals = self::balance($lines);
        if ($totals['debit'] !== $totals['credit']) {
            return array(
                'status' => 400,
                'body' => array(
                    'code' => 'UNBALANCED_JOURNAL',
                    'debit' => $totals['debit'],
                    'credit' => $totals['credit'],
                ),
            );
        }
        $this->pdo->beginTransaction();
        try {
            $pre = $this->handle($body);
            if ($pre['status'] !== 200 || !empty($pre['replay'])) {
                $this->pdo->rollBack();
                return $pre;
            }
            $doc = call_user_func($persistDoc, $body);
            if (!is_array($doc) || empty($doc['id'])) {
                throw new Exception('DOC_ID_REQUIRED');
            }
            $this->insertReceipt(
                $body['event_id'],
                $body['payload_hash'],
                (string)$doc['id'],
                isset($doc['code']) ? $doc['code'] : null
            );
            $this->pdo->commit();
            return array(
                'status' => 200,
                'replay' => false,
                'body' => array(
                    'id' => (string)$doc['id'],
                    'code' => isset($doc['code']) ? $doc['code'] : null,
                ),
            );
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $sqlState = isset($e->errorInfo[0]) ? $e->errorInfo[0] : '';
            if ($sqlState === '23000') {
                $again = $this->handle($body);
                if (!empty($again['replay']) || (isset($again['status']) && $again['status'] === 409)) {
                    return $again;
                }
            }
            throw $e;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findReceipt($eventId)
    {
        $st = $this->pdo->prepare(
            'SELECT event_id, payload_hash, doc_id, doc_code
               FROM integration_event_receipts
              WHERE business_id = ? AND external_source = ? AND event_id = ?
              LIMIT 1'
        );
        $st->execute(array($this->businessId, self::SOURCE, $eventId));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertReceipt($eventId, $payloadHash, $docId, $docCode)
    {
        $st = $this->pdo->prepare(
            'INSERT INTO integration_event_receipts
               (business_id, external_source, event_id, payload_hash, doc_id, doc_code, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute(array($this->businessId, self::SOURCE, $eventId, $payloadHash, $docId, $docCode));
    }
}
