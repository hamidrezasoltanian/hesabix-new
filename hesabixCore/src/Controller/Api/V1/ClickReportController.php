<?php

namespace App\Controller\Api\V1;

use App\Entity\BankAccount;
use App\Entity\Cheque;
use App\Entity\HesabdariDoc;
use App\Entity\HesabdariRow;
use App\Entity\Person;
use App\Service\Security\HmacAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/click/sync')]
class ClickReportController extends AbstractController
{
    public function __construct(
        private HmacAuthenticator $authenticator,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/catalog/persons', name: 'api_v1_click_sync_catalog_persons', methods: ['POST'])]
    public function catalogPersons(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $q = trim((string)((json_decode($request->getContent(), true)['q'] ?? '')));
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Person::class, 'p')
            ->where('p.bid = :bid')
            ->setParameter('bid', $token->getBid())
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(400);
        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.nikename LIKE :q OR p.code LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }
        $items = [];
        foreach ($qb->getQuery()->getResult() as $p) {
            $items[] = [
                'id' => $p->getId(),
                'code' => $p->getCode(),
                'name' => $p->getName() ?: $p->getNikename(),
            ];
        }
        return $this->json(['success' => true, 'items' => $items]);
    }

    #[Route('/catalog/banks', name: 'api_v1_click_sync_catalog_banks', methods: ['POST'])]
    public function catalogBanks(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $rows = $this->em->createQuery(
            'SELECT b.id AS id, b.code AS code, b.name AS name,
                    COALESCE(SUM(r.bd), 0) AS debit, COALESCE(SUM(r.bs), 0) AS credit
               FROM App\Entity\BankAccount b
               LEFT JOIN App\Entity\HesabdariRow r WITH r.bank = b
              WHERE b.bid = :bid
           GROUP BY b.id, b.code, b.name
           ORDER BY b.id DESC'
        )->setParameter('bid', $token->getBid())
            ->setMaxResults(200)
            ->getArrayResult();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => $r['id'],
                'code' => $r['code'],
                'name' => $r['name'],
                'balance' => ((float)$r['debit']) - ((float)$r['credit']),
            ];
        }
        return $this->json(['success' => true, 'items' => $items]);
    }

    #[Route('/reports/expenses', name: 'api_v1_click_sync_reports_expenses', methods: ['POST'])]
    public function expenses(Request $request): JsonResponse
    {
        return $this->docsOfType($request, ['cost']);
    }

    #[Route('/reports/purchases', name: 'api_v1_click_sync_reports_purchases', methods: ['POST'])]
    public function purchases(Request $request): JsonResponse
    {
        return $this->docsOfType($request, ['buy', 'buy_send']);
    }

    #[Route('/reports/cheques', name: 'api_v1_click_sync_reports_cheques', methods: ['POST'])]
    public function cheques(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        [, $limit, $afterId] = $this->paging($request);
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Cheque::class, 'c')
            ->where('c.bid = :bid')
            ->setParameter('bid', $token->getBid())
            ->orderBy('c.id', 'ASC')
            ->setMaxResults($limit);
        if ($afterId > 0) {
            $qb->andWhere('c.id > :afterId')->setParameter('afterId', $afterId);
        }
        $cheques = $qb->getQuery()->getResult();
        $items = [];
        foreach ($cheques as $c) {
            $person = $c->getPerson();
            $bank = $c->getBank();
            $items[] = [
                'id' => $c->getId(),
                'type' => $c->getType(),
                'number' => $c->getNumber(),
                'amount' => $c->getAmount(),
                'payDate' => $c->getPayDate(),
                'date' => $c->getDate(),
                'status' => $c->getStatus(),
                'rejected' => (bool)$c->isRejected(),
                'bank_name' => $bank ? $bank->getName() : $c->getBankOncheque(),
                'person_name' => $person ? ($person->getName() ?: $person->getNikename()) : null,
            ];
        }
        $last = $items ? $items[array_key_last($items)] : null;
        $next = $last ? (string)$last['id'] : null;
        return $this->json(['success' => true, 'items' => $items, 'next_cursor' => $next]);
    }

    #[Route('/reports/banks', name: 'api_v1_click_sync_reports_banks', methods: ['POST'])]
    public function banks(Request $request): JsonResponse
    {
        return $this->catalogBanks($request);
    }

    #[Route('/reports/gl-entries', name: 'api_v1_click_sync_reports_gl', methods: ['POST'])]
    public function glEntries(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        [, $limit, $afterId] = $this->paging($request);
        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(HesabdariDoc::class, 'd')
            ->where('d.bid = :bid')
            ->setParameter('bid', $token->getBid())
            ->orderBy('d.id', 'ASC')
            ->setMaxResults($limit);
        if ($afterId > 0) {
            $qb->andWhere('d.id > :afterId')->setParameter('afterId', $afterId);
        }
        $docs = $qb->getQuery()->getResult();
        $items = [];
        $maxDocId = $afterId;
        foreach ($docs as $doc) {
            $maxDocId = max($maxDocId, (int)$doc->getId());
            $tombstone = $this->isCancelledDoc($doc);
            foreach ($doc->getHesabdariRows() as $row) {
                $ref = $row->getRef();
                $person = $row->getPerson();
                $bank = $row->getBank();
                $items[] = [
                    'id' => $row->getId(),
                    'doc_id' => $doc->getId(),
                    'doc_code' => $doc->getCode(),
                    'date' => $doc->getDate(),
                    'doc_type' => $doc->getType(),
                    'account_code' => $ref ? $ref->getCode() : null,
                    'account_name' => $ref ? $ref->getName() : null,
                    'debit' => $row->getBd(),
                    'credit' => $row->getBs(),
                    'person_id' => $person ? $person->getId() : null,
                    'bank_id' => $bank ? $bank->getId() : null,
                    'tombstone' => $tombstone,
                ];
            }
        }
        return $this->json(['success' => true, 'items' => $items, 'next_cursor' => $maxDocId ? (string)$maxDocId : null]);
    }

    #[Route('/reports/ap-open', name: 'api_v1_click_sync_reports_ap', methods: ['POST'])]
    public function apOpen(Request $request): JsonResponse
    {
        return $this->docsOfType($request, ['buy', 'buy_send']);
    }

    private function paging(Request $request): array
    {
        $body = json_decode((string)$request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }
        $limit = (int)($body['limit'] ?? 200);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }
        $afterId = 0;
        if (isset($body['after_id']) && is_numeric($body['after_id'])) {
            $afterId = (int)$body['after_id'];
        } elseif (isset($body['updated_after']) && is_numeric($body['updated_after'])) {
            $afterId = (int)$body['updated_after'];
        }
        return [$body, $limit, $afterId];
    }

    private function isCancelledDoc(HesabdariDoc $doc): bool
    {
        $status = strtolower((string)$doc->getStatus());
        return in_array($status, ['0', 'cancel', 'cancelled', 'void', 'deleted'], true);
    }

    private function docsOfType(Request $request, array $types): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        [, $limit, $afterId] = $this->paging($request);
        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(HesabdariDoc::class, 'd')
            ->where('d.bid = :bid')
            ->andWhere('d.type IN (:types)')
            ->setParameter('bid', $token->getBid())
            ->setParameter('types', $types)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults($limit);
        if ($afterId > 0) {
            $qb->andWhere('d.id > :afterId')->setParameter('afterId', $afterId);
        }
        $docs = $qb->getQuery()->getResult();
        $items = [];
        $maxId = $afterId;
        $seen = [];
        foreach ($docs as $doc) {
            $maxId = max($maxId, (int)$doc->getId());
            $items[] = $this->mapDocItem($doc);
            $seen[(int)$doc->getId()] = true;
        }
        // Incremental cursor is id-based; cancelled older docs must still re-appear as tombstones.
        if ($afterId > 0) {
            foreach ($this->cancelledDocs($token->getBid(), $types, $afterId) as $doc) {
                $id = (int)$doc->getId();
                if (isset($seen[$id])) {
                    continue;
                }
                $items[] = $this->mapDocItem($doc);
            }
        }
        return $this->json([
            'success' => true,
            'items' => $items,
            'next_cursor' => $maxId ? (string)$maxId : null,
            'official' => false,
        ], Response::HTTP_OK);
    }

    private function cancelledDocs($bid, array $types, int $maxId): array
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(HesabdariDoc::class, 'd')
            ->where('d.bid = :bid')
            ->andWhere('d.type IN (:types)')
            ->andWhere('d.id <= :maxId')
            ->andWhere('LOWER(d.status) IN (:st)')
            ->setParameter('bid', $bid)
            ->setParameter('types', $types)
            ->setParameter('maxId', $maxId)
            ->setParameter('st', ['0', 'cancel', 'cancelled', 'void', 'deleted'])
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }

    private function mapDocItem(HesabdariDoc $doc): array
    {
        $personName = null;
        $personId = null;
        $accountCode = null;
        foreach ($doc->getHesabdariRows() as $row) {
            if ($row->getPerson() && !$personId) {
                $personId = $row->getPerson()->getId();
                $personName = $row->getPerson()->getName() ?: $row->getPerson()->getNikename();
            }
            if ($row->getRef() && !$accountCode) {
                $accountCode = $row->getRef()->getCode();
            }
        }
        return [
            'id' => $doc->getId(),
            'code' => $doc->getCode(),
            'date' => $doc->getDate(),
            'amount' => $doc->getAmount(),
            'remaining' => null,
            'due_date' => null,
            'description' => $doc->getDes(),
            'account_code' => $accountCode,
            'person_id' => $personId,
            'person_name' => $personName,
            'doc_code' => $doc->getCode(),
            'tombstone' => $this->isCancelledDoc($doc),
        ];
    }
}
