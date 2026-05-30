<?php

namespace App\Controller;

use App\Entity\Commodity;
use App\Entity\Person;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PurchaseOrderController extends AbstractController
{
    #[Route('/api/storeroom/po/create', name: 'app_storeroom_po_create', methods: ['POST'])]
    public function create(
        Request $request,
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        if (empty($params['date'])) {
            return $this->json(['result' => -1, 'message' => 'date is required']);
        }

        // Generate code
        $existingCount = $em->createQueryBuilder()
            ->select('COUNT(po.id)')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.bid = :bid')
            ->andWhere('po.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->getQuery()
            ->getSingleScalarResult();
        $sequence = (int)$existingCount + 1;
        $yearLabel = $acc['year']->getLabel() ?? date('Y');
        $code = 'PO-' . $yearLabel . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);

        $person = null;
        if (!empty($params['personId'])) {
            $person = $em->getRepository(Person::class)->findOneBy([
                'id' => $params['personId'],
                'bid' => $acc['bid'],
            ]);
        }

        $po = new PurchaseOrder();
        $po->setBid($acc['bid']);
        $po->setYear($acc['year']);
        $po->setCode($code);
        $po->setDate($params['date']);
        $po->setPerson($person);
        $po->setStatus('draft');
        $po->setNotes($params['notes'] ?? null);
        $po->setCreatedBy($acc['user']);

        $em->persist($po);

        $items = $params['items'] ?? [];
        $total = 0;
        foreach ($items as $itemData) {
            if (empty($itemData['commodityId'])) continue;

            $commodity = $em->getRepository(Commodity::class)->findOneBy([
                'id' => $itemData['commodityId'],
                'bid' => $acc['bid'],
            ]);
            if (!$commodity) continue;

            $poItem = new PurchaseOrderItem();
            $poItem->setPo($po);
            $poItem->setCommodity($commodity);
            $poItem->setQty($itemData['qty'] ?? '1');
            $poItem->setUnitPrice($itemData['unitPrice'] ?? null);
            $poItem->setNotes($itemData['notes'] ?? null);
            $em->persist($poItem);

            if (!empty($itemData['unitPrice']) && !empty($itemData['qty'])) {
                $total += (float)$itemData['qty'] * (float)$itemData['unitPrice'];
            }
        }

        $po->setTotalAmount((string)$total);
        $em->flush();

        return $this->json(['result' => 1, 'id' => $po->getId(), 'code' => $code]);
    }

    #[Route('/api/storeroom/po/update/{id}', name: 'app_storeroom_po_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        $po = $em->getRepository(PurchaseOrder::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$po) {
            return $this->json(['result' => -1, 'message' => 'PO not found']);
        }
        if ($po->getStatus() !== 'draft') {
            return $this->json(['result' => -2, 'message' => 'Only draft POs can be updated']);
        }

        if (!empty($params['date'])) $po->setDate($params['date']);
        if (array_key_exists('notes', $params)) $po->setNotes($params['notes']);

        if (!empty($params['personId'])) {
            $person = $em->getRepository(Person::class)->findOneBy([
                'id' => $params['personId'],
                'bid' => $acc['bid'],
            ]);
            $po->setPerson($person);
        }

        // Update items if provided
        if (isset($params['items'])) {
            foreach ($po->getItems() as $item) {
                $em->remove($item);
            }
            $em->flush();

            $total = 0;
            foreach ($params['items'] as $itemData) {
                if (empty($itemData['commodityId'])) continue;
                $commodity = $em->getRepository(Commodity::class)->findOneBy([
                    'id' => $itemData['commodityId'],
                    'bid' => $acc['bid'],
                ]);
                if (!$commodity) continue;

                $poItem = new PurchaseOrderItem();
                $poItem->setPo($po);
                $poItem->setCommodity($commodity);
                $poItem->setQty($itemData['qty'] ?? '1');
                $poItem->setUnitPrice($itemData['unitPrice'] ?? null);
                $poItem->setNotes($itemData['notes'] ?? null);
                $em->persist($poItem);

                if (!empty($itemData['unitPrice']) && !empty($itemData['qty'])) {
                    $total += (float)$itemData['qty'] * (float)$itemData['unitPrice'];
                }
            }
            $po->setTotalAmount((string)$total);
        }

        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/po/approve/{id}', name: 'app_storeroom_po_approve', methods: ['POST'])]
    public function approve(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $po = $em->getRepository(PurchaseOrder::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$po) {
            return $this->json(['result' => -1, 'message' => 'PO not found']);
        }

        $po->setStatus('approved');
        $po->setApprovedBy($acc['user']);
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/po/receive/{id}', name: 'app_storeroom_po_receive', methods: ['POST'])]
    public function receive(
        int $id,
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        $po = $em->getRepository(PurchaseOrder::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$po) {
            return $this->json(['result' => -1, 'message' => 'PO not found']);
        }

        // Update received qty per item
        $receivedQtys = $params['receivedQty'] ?? [];
        foreach ($po->getItems() as $item) {
            $itemId = $item->getId();
            if (isset($receivedQtys[$itemId])) {
                $item->setReceivedQty((string)$receivedQtys[$itemId]);
            }
        }

        $po->setStatus('received');
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/po/cancel/{id}', name: 'app_storeroom_po_cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $po = $em->getRepository(PurchaseOrder::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$po) {
            return $this->json(['result' => -1, 'message' => 'PO not found']);
        }

        $po->setStatus('cancelled');
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/po/list', name: 'app_storeroom_po_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $pos = $em->createQueryBuilder()
            ->select('po')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.bid = :bid')
            ->andWhere('po.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->orderBy('po.id', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($pos as $po) {
            $result[] = [
                'id' => $po->getId(),
                'code' => $po->getCode(),
                'date' => $po->getDate(),
                'status' => $po->getStatus(),
                'person' => $po->getPerson() ? $po->getPerson()->getNikename() : null,
                'itemCount' => $po->getItems()->count(),
                'totalAmount' => $po->getTotalAmount(),
                'createdBy' => $po->getCreatedBy() ? $po->getCreatedBy()->getFullName() : null,
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/po/info/{id}', name: 'app_storeroom_po_info', methods: ['GET'])]
    public function info(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $po = $em->getRepository(PurchaseOrder::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$po) {
            return $this->json(['result' => -1, 'message' => 'PO not found']);
        }

        $items = [];
        foreach ($po->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'commodity' => $item->getCommodity() ? [
                    'id' => $item->getCommodity()->getId(),
                    'name' => $item->getCommodity()->getName(),
                    'code' => $item->getCommodity()->getCode(),
                ] : null,
                'qty' => $item->getQty(),
                'unitPrice' => $item->getUnitPrice(),
                'receivedQty' => $item->getReceivedQty(),
                'notes' => $item->getNotes(),
            ];
        }

        return $this->json([
            'result' => 1,
            'po' => [
                'id' => $po->getId(),
                'code' => $po->getCode(),
                'date' => $po->getDate(),
                'status' => $po->getStatus(),
                'notes' => $po->getNotes(),
                'totalAmount' => $po->getTotalAmount(),
                'person' => $po->getPerson() ? [
                    'id' => $po->getPerson()->getId(),
                    'name' => $po->getPerson()->getNikename(),
                ] : null,
                'createdBy' => $po->getCreatedBy() ? $po->getCreatedBy()->getFullName() : null,
                'approvedBy' => $po->getApprovedBy() ? $po->getApprovedBy()->getFullName() : null,
                'items' => $items,
            ],
        ]);
    }
}
